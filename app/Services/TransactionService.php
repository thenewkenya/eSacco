<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class TransactionService
{
    // Transaction limits (in KES)
    const DAILY_WITHDRAWAL_LIMIT = 100000; // KES 100,000
    const SINGLE_TRANSACTION_LIMIT = 500000; // KES 500,000
    const MINIMUM_BALANCE = 1000; // KES 1,000 minimum balance
    const LARGE_TRANSACTION_THRESHOLD = 50000; // Requires approval

    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Process a deposit transaction
     */
    public function processDeposit(Account $account, float $amount, ?string $description = null, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($account, $amount, $description, $metadata) {
            // Validation
            $this->validateAmount($amount);
            $this->validateAccountStatus($account);
            $this->validateAccountPermissions($account);

            // Record balance before transaction
            $balanceBefore = $account->balance;
            $requiresApproval = $amount >= self::LARGE_TRANSACTION_THRESHOLD;
            $status = $requiresApproval ? Transaction::STATUS_PENDING : Transaction::STATUS_COMPLETED;

            // Only update account balance if transaction doesn't require approval
            if (!$requiresApproval) {
                $account->balance += $amount;
                $account->save();
                $balanceAfter = $account->balance;
            } else {
                // For pending transactions, balance_after shows what it would be
                $balanceAfter = $balanceBefore + $amount;
            }

            // Create transaction record
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'member_id' => $account->member_id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'description' => $description ?? "Deposit to {$account->account_type} account",
                'reference_number' => $this->generateReferenceNumber(),
                'status' => $status,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => array_merge($metadata, [
                    'processed_by' => auth()->id(),
                    'processing_time' => now()->toISOString(),
                    'requires_approval' => $requiresApproval,
                ]),
            ]);

            // Send notifications
            $this->notificationService->sendTransactionNotification($transaction, 'created');
            
            // Send large deposit notification if applicable
            if ($amount >= 50000) {
                $this->notificationService->sendLargeDepositNotification($transaction);
            }

            // Log the transaction
            Log::info('Deposit processed', [
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'balance_after' => $account->balance,
            ]);

            return $transaction;
        });
    }

    /**
     * Process a withdrawal transaction
     */
    public function processWithdrawal(Account $account, float $amount, ?string $description = null, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($account, $amount, $description, $metadata) {
            // Validation
            $this->validateAmount($amount);
            $this->validateAccountStatus($account);
            $this->validateAccountPermissions($account);
            $this->validateWithdrawalLimits($account, $amount);

            // Check sufficient balance
            $remainingBalance = $account->balance - $amount;
            if ($remainingBalance < self::MINIMUM_BALANCE) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient funds. Minimum balance of KES " . number_format(self::MINIMUM_BALANCE) . " required."
                ]);
            }

            // Record balance before transaction
            $balanceBefore = $account->balance;
            $requiresApproval = $amount >= self::LARGE_TRANSACTION_THRESHOLD;
            $status = $requiresApproval ? Transaction::STATUS_PENDING : Transaction::STATUS_COMPLETED;

            // Only update account balance if transaction doesn't require approval
            if (!$requiresApproval) {
                $account->balance -= $amount;
                $account->save();
                $balanceAfter = $account->balance;
            } else {
                // For pending transactions, balance_after shows what it would be
                $balanceAfter = $balanceBefore - $amount;
            }

            // Create transaction record
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'member_id' => $account->member_id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'description' => $description ?? "Withdrawal from {$account->account_type} account",
                'reference_number' => $this->generateReferenceNumber(),
                'status' => $status,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => array_merge($metadata, [
                    'processed_by' => auth()->id(),
                    'processing_time' => now()->toISOString(),
                    'requires_approval' => $requiresApproval,
                ]),
            ]);

            // Send notifications
            $this->notificationService->sendTransactionNotification($transaction, 'created');

            // Log the transaction
            Log::info('Withdrawal processed', [
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'balance_after' => $account->balance,
            ]);

            return $transaction;
        });
    }

    /**
     * Process a transfer between accounts
     */
    public function processTransfer(Account $fromAccount, Account $toAccount, float $amount, ?string $description = null, array $metadata = []): array
    {
        return DB::transaction(function () use ($fromAccount, $toAccount, $amount, $description, $metadata) {
            // Validation
            $this->validateAmount($amount);
            $this->validateAccountStatus($fromAccount);
            $this->validateAccountStatus($toAccount);
            $this->validateTransferAccounts($fromAccount, $toAccount);
            $this->validateTransferPermissions($fromAccount);

            // Check sufficient balance for source account
            $remainingBalance = $fromAccount->balance - $amount;
            if ($remainingBalance < self::MINIMUM_BALANCE) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient funds in source account. Minimum balance of KES " . number_format(self::MINIMUM_BALANCE) . " required."
                ]);
            }

            $transferReference = $this->generateReferenceNumber();
            $debitReference = $this->generateReferenceNumber();
            $creditReference = $this->generateReferenceNumber();
            $requiresApproval = $amount >= self::LARGE_TRANSACTION_THRESHOLD;
            $status = $requiresApproval ? Transaction::STATUS_PENDING : Transaction::STATUS_COMPLETED;

            // Record balances before transactions
            $fromBalanceBefore = $fromAccount->balance;
            $toBalanceBefore = $toAccount->balance;

            // Only update account balances if transaction doesn't require approval
            if (!$requiresApproval) {
                $fromAccount->balance -= $amount;
                $toAccount->balance += $amount;
                $fromAccount->save();
                $toAccount->save();
                $fromBalanceAfter = $fromAccount->balance;
                $toBalanceAfter = $toAccount->balance;
            } else {
                // For pending transactions, balance_after shows what it would be
                $fromBalanceAfter = $fromBalanceBefore - $amount;
                $toBalanceAfter = $toBalanceBefore + $amount;
            }

            $transferMetadata = array_merge($metadata, [
                'processed_by' => auth()->id(),
                'processing_time' => now()->toISOString(),
                'requires_approval' => $requiresApproval,
                'transfer_reference' => $transferReference,
            ]);

            // Create debit transaction (from account)
            $debitTransaction = Transaction::create([
                'account_id' => $fromAccount->id,
                'member_id' => $fromAccount->member_id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount, // Store positive amount, metadata indicates direction
                'description' => $description ?? "Transfer to {$toAccount->account_number}",
                'reference_number' => $debitReference,
                'status' => $status,
                'balance_before' => $fromBalanceBefore,
                'balance_after' => $fromBalanceAfter,
                'metadata' => array_merge($transferMetadata, [
                    'transfer_type' => 'debit',
                    'destination_account' => $toAccount->account_number,
                    'linked_transaction' => $creditReference,
                ]),
            ]);

            // Create credit transaction (to account)
            $creditTransaction = Transaction::create([
                'account_id' => $toAccount->id,
                'member_id' => $toAccount->member_id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount, // Positive for credit
                'description' => $description ?? "Transfer from {$fromAccount->account_number}",
                'reference_number' => $creditReference,
                'status' => $status,
                'balance_before' => $toBalanceBefore,
                'balance_after' => $toBalanceAfter,
                'metadata' => array_merge($transferMetadata, [
                    'transfer_type' => 'credit',
                    'source_account' => $fromAccount->account_number,
                    'linked_transaction' => $debitReference,
                ]),
            ]);

            // Send notifications for both transactions
            $this->notificationService->sendTransactionNotification($debitTransaction, 'created');
            $this->notificationService->sendTransactionNotification($creditTransaction, 'created');

            // Log the transfer
            Log::info('Transfer processed', [
                'transfer_reference' => $transferReference,
                'debit_reference' => $debitReference,
                'credit_reference' => $creditReference,
                'from_account' => $fromAccount->account_number,
                'to_account' => $toAccount->account_number,
                'amount' => $amount,
                'from_balance_after' => $fromAccount->balance,
                'to_balance_after' => $toAccount->balance,
            ]);

            return [
                'debit_transaction' => $debitTransaction,
                'credit_transaction' => $creditTransaction,
                'reference_number' => $transferReference,
            ];
        });
    }

    /**
     * Approve a pending transaction
     */
    public function approveTransaction(Transaction $transaction, User $approver): bool
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaction is not pending approval.'
            ]);
        }

        // Check approver permissions
        if (!in_array($approver->role, ['admin', 'manager'])) {
            throw ValidationException::withMessages([
                'approver' => 'Insufficient permissions to approve transactions.'
            ]);
        }

        return DB::transaction(function () use ($transaction, $approver) {
            // Apply the balance changes now that transaction is approved
            $account = $transaction->account;
            
            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $account->balance += $transaction->amount;
            } elseif ($transaction->type === Transaction::TYPE_WITHDRAWAL) {
                $account->balance -= $transaction->amount;
            } elseif ($transaction->type === Transaction::TYPE_TRANSFER) {
                // Handle transfer transactions
                if ($transaction->amount > 0) {
                    // Credit transaction
                    $account->balance += $transaction->amount;
                } else {
                    // Debit transaction
                    $account->balance += $transaction->amount; // amount is negative for debits
                }
            }
            
            $account->save();
            
            // Update transaction status and metadata
            $transaction->update([
                'status' => Transaction::STATUS_COMPLETED,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'approved_by' => $approver->id,
                    'approved_at' => now()->toISOString(),
                    'approval_comments' => 'Transaction approved',
                ]),
            ]);

            // Send approval notification
            $this->notificationService->sendTransactionNotification($transaction, 'approved');

            Log::info('Transaction approved', [
                'transaction_id' => $transaction->id,
                'approved_by' => $approver->id,
                'amount' => $transaction->amount,
                'account_balance_after' => $account->balance,
            ]);

            return true;
        });
    }

    /**
     * Reject a pending transaction and reverse balances
     */
    public function rejectTransaction(Transaction $transaction, User $approver, string $reason = ''): bool
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaction is not pending approval.'
            ]);
        }

        return DB::transaction(function () use ($transaction, $approver, $reason) {
            // No need to reverse balance changes since they were never applied for pending transactions
            
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'rejected_by' => $approver->id,
                    'rejected_at' => now()->toISOString(),
                    'rejection_reason' => $reason ?: 'Transaction rejected',
                ]),
            ]);

            // Send rejection notification
            $this->notificationService->sendTransactionNotification($transaction, 'failed');

            Log::info('Transaction rejected', [
                'transaction_id' => $transaction->id,
                'rejected_by' => $approver->id,
                'reason' => $reason,
            ]);

            return true;
        });
    }

    /**
     * Get transaction summary for an account
     */
    public function getAccountTransactionSummary(Account $account, int $days = 30): array
    {
        $transactions = $account->transactions()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', Transaction::STATUS_COMPLETED)
            ->get();

        $deposits = $transactions->where('type', Transaction::TYPE_DEPOSIT)->sum('amount');
        $withdrawals = $transactions->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount');
        $transfers = $transactions->where('type', Transaction::TYPE_TRANSFER);

        return [
            'total_transactions' => $transactions->count(),
            'total_deposits' => $deposits,
            'total_withdrawals' => $withdrawals,
            'total_transfers_in' => $transfers->where('amount', '>', 0)->sum('amount'),
            'total_transfers_out' => abs($transfers->where('amount', '<', 0)->sum('amount')),
            'net_change' => $deposits - $withdrawals,
            'current_balance' => $account->balance,
        ];
    }

    /**
     * Get daily withdrawal total for limits
     */
    public function getDailyWithdrawalTotal(Account $account): float
    {
        return $account->transactions()
            ->where('type', Transaction::TYPE_WITHDRAWAL)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    /**
     * Generate unique reference number
     */
    private function generateReferenceNumber(): string
    {
        return 'TXN' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Validate transaction amount
     */
    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Transaction amount must be greater than zero.'
            ]);
        }

        if ($amount > self::SINGLE_TRANSACTION_LIMIT) {
            throw ValidationException::withMessages([
                'amount' => 'Transaction amount exceeds single transaction limit of KES ' . number_format(self::SINGLE_TRANSACTION_LIMIT) . '.'
            ]);
        }
    }

    /**
     * Validate account status
     */
    private function validateAccountStatus(Account $account): void
    {
        if ($account->status !== Account::STATUS_ACTIVE) {
            throw new \Exception('Account is not active. Current status: ' . $account->status);
        }
    }

    /**
     * Validate withdrawal limits
     */
    private function validateWithdrawalLimits(Account $account, float $amount): void
    {
        $dailyTotal = $this->getDailyWithdrawalTotal($account);
        
        if (($dailyTotal + $amount) > self::DAILY_WITHDRAWAL_LIMIT) {
            throw ValidationException::withMessages([
                'amount' => 'Daily withdrawal limit of KES ' . number_format(self::DAILY_WITHDRAWAL_LIMIT) . ' would be exceeded. Today\'s total: KES ' . number_format($dailyTotal)
            ]);
        }
    }

    /**
     * Validate transfer accounts
     */
    private function validateTransferAccounts(Account $fromAccount, Account $toAccount): void
    {
        if ($fromAccount->id === $toAccount->id) {
            throw ValidationException::withMessages([
                'account' => 'Cannot transfer to the same account.'
            ]);
        }

        if ($fromAccount->currency !== $toAccount->currency) {
            throw ValidationException::withMessages([
                'account' => 'Cannot transfer between accounts with different currencies.'
            ]);
        }
    }

    /**
     * Validate account permissions based on user role
     */
    private function validateAccountPermissions(Account $account): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => 'User must be authenticated to perform transactions.'
            ]);
        }

        // Members can only perform transactions on their own accounts
        if ($user->role === 'member' && $account->member_id !== $user->id) {
            throw ValidationException::withMessages([
                'account_id' => 'You can only perform transactions on your own accounts.'
            ]);
        }

        // Staff (admin, manager, staff) can perform transactions on any account
        // No additional validation needed for staff roles
    }

    /**
     * Validate transfer permissions based on user role
     */
    private function validateTransferPermissions(Account $fromAccount): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => 'User must be authenticated to perform transfers.'
            ]);
        }

        // Members can only transfer from their own accounts
        if ($user->role === 'member' && $fromAccount->member_id !== $user->id) {
            throw ValidationException::withMessages([
                'from_account_id' => 'You can only transfer from your own accounts.'
            ]);
        }

        // Staff (admin, manager, staff) can transfer from any account
        // No additional validation needed for staff roles
    }

    /**
     * Alias for processDeposit - for controller compatibility
     */
    public function createDeposit(Account $account, float $amount, ?string $description = null, ?User $processedBy = null): Transaction
    {
        $metadata = [];
        if ($processedBy) {
            $metadata['processed_by'] = $processedBy->id;
        }
        return $this->processDeposit($account, $amount, $description, $metadata);
    }

    /**
     * Alias for processWithdrawal - for controller compatibility
     */
    public function createWithdrawal(Account $account, float $amount, ?string $description = null, ?User $processedBy = null): Transaction
    {
        $metadata = [];
        if ($processedBy) {
            $metadata['processed_by'] = $processedBy->id;
        }
        
        $transaction = $this->processWithdrawal($account, $amount, $description, $metadata);
        
        // Check if fee should be applied (from request context)
        $request = request();
        if ($request && $request->has('apply_fee') && $request->apply_fee) {
            $this->processFee($account, $amount, $description, $processedBy);
        }
        
        return $transaction;
    }

    /**
     * Process transaction fee
     */
    private function processFee(Account $account, float $transactionAmount, ?string $description = null, ?User $processedBy = null): Transaction
    {
        // Calculate fee (1% of transaction amount, minimum 5 KES)
        $feeAmount = max(5.00, $transactionAmount * 0.01);
        
        // Create fee transaction
        return Transaction::create([
            'account_id' => $account->id,
            'member_id' => $account->member_id,
            'type' => Transaction::TYPE_FEE,
            'amount' => $feeAmount,
            'description' => $description ? $description . ' fee' : 'Transaction fee',
            'reference_number' => $this->generateReferenceNumber(),
            'status' => Transaction::STATUS_COMPLETED,
            'balance_before' => $account->balance,
            'balance_after' => $account->balance - $feeAmount,
            'metadata' => [
                'fee_for_amount' => $transactionAmount,
                'fee_rate' => 0.01,
                'processed_by' => $processedBy ? $processedBy->id : auth()->id(),
                'processing_time' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Alias for processTransfer - for controller compatibility
     */
    public function createTransfer(Account $fromAccount, Account $toAccount, float $amount, ?string $description = null, ?User $processedBy = null): Transaction
    {
        $metadata = [];
        if ($processedBy) {
            $metadata['processed_by'] = $processedBy->id;
        }
        $result = $this->processTransfer($fromAccount, $toAccount, $amount, $description, $metadata);
        // Return the debit transaction (first transaction in the transfer)
        return $result['debit_transaction'];
    }

    /**
     * Reverse a completed transaction
     */
    public function reverseTransaction(Transaction $transaction, string $reason, User $reversedBy): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason, $reversedBy) {
            // Validate transaction can be reversed
            if ($transaction->status !== Transaction::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'transaction' => 'Only completed transactions can be reversed.'
                ]);
            }

            // Check if already reversed
            if (isset($transaction->metadata['reversed']) && $transaction->metadata['reversed']) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaction has already been reversed.'
                ]);
            }

            $account = $transaction->account;
            $balanceBefore = $account->balance;

            // Reverse the transaction effect on account balance
            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                // For deposit reversal, subtract the amount
                $account->balance -= $transaction->amount;
                $reversalType = Transaction::TYPE_WITHDRAWAL;
            } else {
                // For withdrawal reversal, add the amount back
                $account->balance += $transaction->amount;
                $reversalType = Transaction::TYPE_DEPOSIT;
            }

            $account->save();

            // Create reversal transaction
            $reversalTransaction = Transaction::create([
                'account_id' => $account->id,
                'member_id' => $account->member_id,
                'type' => $reversalType,
                'amount' => $transaction->amount,
                'description' => "Reversal: {$reason}",
                'reference_number' => $this->generateReferenceNumber(),
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'metadata' => [
                    'reversal_of' => $transaction->id,
                    'reversal_reason' => $reason,
                    'reversed_by' => $reversedBy->id,
                    'reversal_time' => now()->toISOString(),
                ],
            ]);

            // Mark original transaction as reversed
            $originalMetadata = $transaction->metadata ?? [];
            $originalMetadata['reversed'] = true;
            $originalMetadata['reversed_by'] = $reversedBy->id;
            $originalMetadata['reversal_transaction_id'] = $reversalTransaction->id;
            $originalMetadata['reversal_time'] = now()->toISOString();
            $originalMetadata['reversal_reason'] = $reason;
            
            $transaction->update([
                'status' => Transaction::STATUS_REVERSED,
                'metadata' => $originalMetadata
            ]);

            // Send notifications
            $this->notificationService->sendTransactionNotification($reversalTransaction, 'created');

            // Log the reversal
            Log::info('Transaction reversed', [
                'original_transaction_id' => $transaction->id,
                'reversal_transaction_id' => $reversalTransaction->id,
                'amount' => $transaction->amount,
                'reason' => $reason,
                'reversed_by' => $reversedBy->id,
            ]);

            return $reversalTransaction;
        });
    }
} 