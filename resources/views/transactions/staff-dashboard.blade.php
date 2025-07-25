@php
    $user = auth()->user();
@endphp

<x-layouts.app :title="__('Transaction Management')">
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
        <!-- Header -->
        <div class="bg-white dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
            <div class="px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ __('Transaction Management') }}
                        </h1>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Monitor and approve transactions</p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                        <flux:button :href="route('transactions.deposit.create')" variant="outline" icon="plus">
                            {{ __('Deposit') }}
                        </flux:button>
                        <flux:button :href="route('transactions.withdrawal.create')" variant="outline" icon="minus">
                            {{ __('Withdrawal') }}
                        </flux:button>
                        <flux:button :href="route('transactions.transfer.create')" variant="primary" icon="arrows-right-left">
                            {{ __('Transfer') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 lg:px-8 py-8">
            <!-- Daily Statistics -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Today's Statistics</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 lg:gap-6">
                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 lg:p-6 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center">
                            <div class="p-2 lg:p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex-shrink-0">
                                <flux:icon.document-text class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="ml-3 lg:ml-4 min-w-0 flex-1">
                                <p class="text-xs lg:text-sm text-zinc-600 dark:text-zinc-400 truncate">Total Transactions</p>
                                <p class="text-lg lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $todayStats['total_transactions'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 lg:p-6 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center">
                            <div class="p-2 lg:p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex-shrink-0">
                                <flux:icon.banknotes class="w-5 h-5 lg:w-6 lg:h-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="ml-3 lg:ml-4 min-w-0 flex-1">
                                <p class="text-xs lg:text-sm text-zinc-600 dark:text-zinc-400 truncate">Total Volume</p>
                                <p class="text-lg lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                    KES {{ number_format($todayStats['total_amount'], 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 lg:p-6 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center">
                            <div class="p-2 lg:p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex-shrink-0">
                                <flux:icon.arrow-down class="w-5 h-5 lg:w-6 lg:h-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="ml-3 lg:ml-4 min-w-0 flex-1">
                                <p class="text-xs lg:text-sm text-zinc-600 dark:text-zinc-400 truncate">Deposits</p>
                                <p class="text-lg lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                    KES {{ number_format($todayStats['deposits'], 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 lg:p-6 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center">
                            <div class="p-2 lg:p-3 bg-red-100 dark:bg-red-900/30 rounded-lg flex-shrink-0">
                                <flux:icon.arrow-up class="w-5 h-5 lg:w-6 lg:h-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="ml-3 lg:ml-4 min-w-0 flex-1">
                                <p class="text-xs lg:text-sm text-zinc-600 dark:text-zinc-400 truncate">Withdrawals</p>
                                <p class="text-lg lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                    KES {{ number_format($todayStats['withdrawals'], 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 lg:p-6 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center">
                            <div class="p-2 lg:p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex-shrink-0">
                                <flux:icon.clock class="w-5 h-5 lg:w-6 lg:h-6 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div class="ml-3 lg:ml-4 min-w-0 flex-1">
                                <p class="text-xs lg:text-sm text-zinc-600 dark:text-zinc-400 truncate">Pending Approvals</p>
                                <p class="text-lg lg:text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $todayStats['pending_approvals'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Simple Filters -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Quick Status Filter -->
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Status:</label>
                            <select name="status" onchange="window.location.href='{{ route('transactions.index') }}?status=' + this.value" class="px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100">
                                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        
                        @if($status === 'pending')
                            <!-- Quick Priority Filter -->
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('transactions.index', ['status' => 'pending', 'priority' => 'high']) }}" 
                                   class="px-3 py-2 text-sm {{ request('priority') === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }} rounded-lg transition-colors">
                                    High Value Only
                                </a>
                                <a href="{{ route('transactions.index', ['status' => 'pending']) }}" 
                                   class="px-3 py-2 text-sm {{ !request('priority') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }} rounded-lg transition-colors">
                                    Show All
                                </a>
                            </div>
                        @endif
                    </div>
                    
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $pendingTransactions->count() }} of {{ $pendingTransactions->total() }} transactions
                    </div>
                </div>
            </div>

            <!-- Pending Transactions -->
            @if($pendingTransactions->count() > 0)
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 mb-8">
                    <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                    <flux:icon.clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $status === 'pending' ? 'Pending Approvals' : ucfirst($status) . ' Transactions' }}
                                    </h2>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $pendingTransactions->count() }} transactions</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Guide -->
                    @if($status === 'pending')
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-6">
                            <div class="flex items-center text-sm text-blue-700 dark:text-blue-300">
                                <flux:icon.information-circle class="w-4 h-4 mr-2 flex-shrink-0" />
                                <span><strong>Quick tip:</strong> Click any transaction card to view full details, or use the Approve/Reject buttons for quick actions</span>
                            </div>
                        </div>
                    @endif

                    <!-- Transactions Cards -->
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($pendingTransactions as $transaction)
                            <div class="group relative">
                                <!-- Clickable Card Background -->
                                <div class="absolute inset-0 cursor-pointer" onclick="openTransactionDetails({{ $transaction->id }})"></div>
                                
                                <div class="p-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            
                                            <div class="p-2 rounded-lg {{ 
                                                $transaction->type === 'deposit' ? 'bg-emerald-100 dark:bg-emerald-900/30' : 
                                                ($transaction->type === 'withdrawal' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-100 dark:bg-blue-900/30') 
                                            }}">
                                                @if($transaction->type === 'deposit')
                                                    <flux:icon.arrow-down class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                                @elseif($transaction->type === 'withdrawal')
                                                    <flux:icon.arrow-up class="w-4 h-4 text-red-600 dark:text-red-400" />
                                                @else
                                                    <flux:icon.arrows-right-left class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                                @endif
                                            </div>
                                            
                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $transaction->member->name }} • {{ ucfirst(str_replace('_', ' ', $transaction->type)) }} • {{ $transaction->created_at->format('M d, Y') }}
                                                    </p>
                                                    @if($transaction->amount >= 50000)
                                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 rounded-full">
                                                            High Value
                                                        </span>
                                                    @endif
                                                    @php
                                                        $hoursOld = $transaction->created_at->diffInHours(now());
                                                    @endphp
                                                    @if($hoursOld > 24)
                                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-full">
                                                            {{ $hoursOld }}h old
                                                        </span>
                                                    @elseif($hoursOld > 8)
                                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full">
                                                            {{ $hoursOld }}h old
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ $transaction->account->account_number }}
                                                </p>
                                                @if($transaction->reference_number)
                                                    <p class="text-xs text-zinc-500 dark:text-zinc-500">
                                                        Ref: {{ $transaction->reference_number }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-4">
                                            <div class="text-right">
                                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">
                                                    KES {{ number_format($transaction->amount, 2) }}
                                                </p>
                                                <span class="px-2 py-1 text-xs font-medium {{ 
                                                    $transaction->status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : 
                                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' 
                                                }} rounded-full">
                                                    {{ ucfirst($transaction->status) }}
                                                </span>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="relative z-10 flex items-center space-x-2">
                                                @if($status === 'pending')
                                                    <flux:button variant="outline" size="sm" onclick="event.stopPropagation(); openTransactionDetails({{ $transaction->id }})">
                                                        {{ __('Review') }}
                                                    </flux:button>
                                                    <flux:modal.trigger name="process-transaction-modal">
                                                        <flux:button variant="primary" size="sm" onclick="event.stopPropagation(); setProcessDetails({{ $transaction->id }}, '{{ strtolower($transaction->type) }}', '{{ number_format($transaction->amount, 0) }}')">
                                                            {{ __('Process') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                        {{ $pendingTransactions->links() }}
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-12 text-center">
                    <div class="p-3 bg-zinc-100 dark:bg-zinc-700 rounded-full w-16 h-16 mx-auto mb-4">
                        <flux:icon.banknotes class="w-10 h-10 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">No transactions found</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 text-lg mb-6">No transactions match your current filters.</p>
                    <flux:button :href="route('transactions.index')" variant="outline" icon="arrow-path">
                        {{ __('Clear Filters') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    <!-- Process Transaction Modal -->
    <flux:modal name="process-transaction-modal" class="md:w-96">
        <div class="space-y-6">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/20">
                    <flux:icon.check class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="mt-3">
                    <flux:heading size="lg">Process Transaction</flux:heading>
                    <div class="mt-2">
                        <flux:subheading id="approveTransactionDetails">
                            Are you sure you want to process this transaction?
                        </flux:subheading>
                    </div>
                </div>
            </div>
            
            <form id="approveForm" method="POST">
                @csrf
                
                <flux:field>
                    <flux:label>Approval Comments (Optional)</flux:label>
                    <flux:textarea name="comments" rows="3" placeholder="Add any comments about this approval..." />
                </flux:field>
                
                <div class="flex gap-3 mt-6">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="flex-1">
                            Cancel
                        </flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" class="flex-1" type="submit">
                        <flux:icon.check class="w-4 h-4 mr-2" />
                        Process Transaction
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>



    <!-- JavaScript -->
    <script>
        // Open transaction details
        function openTransactionDetails(transactionId) {
            window.location.href = `/transactions/${transactionId}`;
        }

        // Individual transaction actions
        function setProcessDetails(transactionId, transactionType, amount) {
            const form = document.getElementById('approveForm');
            const details = document.getElementById('approveTransactionDetails');
            
            form.action = `/transactions/approve/${transactionId}`;
            details.textContent = `Process this ${transactionType} of KES ${amount}`;
            
            // Clear previous comments
            const commentsField = form.querySelector('textarea[name="comments"]');
            if (commentsField) {
                commentsField.value = '';
            }
        }
    </script>
</x-layouts.app> 