// resources/js/Pages/Transactions.tsx

import { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { AppSidebar } from '@/components/app-sidebar';
import { SiteHeader } from '@/components/site-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    PlusIcon,
    RefreshCwIcon,
    TrendingUpIcon,
    TrendingDownIcon,
    WalletIcon,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { DataTableTransactions } from '@/components/data-table-transactions';
import { TransactionFormModal } from '@/components/transaction-form-modal';
import {
    DeleteTransactionDialog,
    BulkDeleteTransactionDialog,
} from '@/components/delete-transaction-dialog';

interface Account {
    id: number;
    name: string;
    type: string;
    color?: string;
    balance: number;
}

interface Category {
    id: number;
    name: string;
    type: string;
    icon?: string;
    color?: string;
}

interface Transaction {
    id: number;
    account_id: number;
    category_id: number;
    transfer_account_id?: number | null;
    description: string;
    amount: number;
    type: 'income' | 'expense' | 'transfer';
    date: string;
    notes?: string | null;
    reference_number?: string | null;
    location?: string | null;
    is_recurring: boolean;
    recurring_type?: string | null;
    recurring_interval?: number | null;
    recurring_end_date?: string | null;
    is_cleared: boolean;
    account?: {
        id: number;
        name: string;
        type: string;
        color?: string;
    };
    category?: {
        id: number;
        name: string;
        icon?: string;
        color?: string;
        type: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTransactions {
    data: Transaction[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Summary {
    total_income: number;
    total_expenses: number;
    total_transactions: number;
}

interface Filters {
    search?: string;
    sort_by?: string;
    sort_order?: string;
    per_page?: number;
    type?: string;
    account_id?: number;
    category_id?: number;
    start_date?: string;
    end_date?: string;
}

interface TransactionFormData {
    account_id: number;
    category_id: number;
    transfer_account_id?: number | null;
    description: string;
    amount: number;
    type: 'income' | 'expense' | 'transfer';
    date: string;
    notes?: string | null;
    reference_number?: string | null;
    location?: string | null;
    is_recurring: boolean;
    recurring_type?: string | null;
    recurring_interval?: number | null;
    recurring_end_date?: string | null;
    is_cleared: boolean;
}

interface PageProps {
    transactions: PaginatedTransactions;
    accounts: Account[];
    categories: Category[];
    summary: Summary;
    filters: Filters;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: any;
}

export default function Transactions() {
    const { transactions, accounts, categories, summary, filters, flash } =
        usePage<PageProps>().props;
    const { toast } = useToast();
    const [selectedTransactions, setSelectedTransactions] = useState<number[]>(
        []
    );
    const [loading, setLoading] = useState(false);

    // Modal states
    const [showFormModal, setShowFormModal] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showBulkDeleteDialog, setShowBulkDeleteDialog] = useState(false);
    const [editingTransaction, setEditingTransaction] =
        useState<Transaction | null>(null);
    const [deletingTransaction, setDeletingTransaction] =
        useState<Transaction | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // Format currency
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast({
                title: 'Success',
                description: flash.success,
            });
        }

        if (flash?.error) {
            toast({
                title: 'Error',
                description: flash.error,
                variant: 'destructive',
            });
        }
    }, [flash, toast]);

    useEffect(() => {
        console.log('Transactions page loaded', transactions);
    }, [transactions]);

    // Handle search
    const handleSearch = (query: string) => {
        router.get(
            '/transactions',
            { ...filters, search: query, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle pagination
    const handlePageChange = (page: number) => {
        router.get(
            '/transactions',
            { ...filters, page },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle sorting
    const handleSort = (sortBy: string) => {
        const sortOrder =
            filters.sort_by === sortBy && filters.sort_order === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            '/transactions',
            { ...filters, sort_by: sortBy, sort_order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle filter change
    const handleFilterChange = (newFilters: Partial<Filters>) => {
        router.get(
            '/transactions',
            { ...filters, ...newFilters, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle create - open modal
    const handleCreate = () => {
        setEditingTransaction(null);
        setShowFormModal(true);
    };

    // Handle edit - open modal with transaction data
    const handleEdit = (transactionId: number) => {
        const transaction = transactions.data.find(
            (t) => t.id === transactionId
        );
        if (transaction) {
            setEditingTransaction(transaction);
            setShowFormModal(true);
        }
    };

    // Handle form submit (create or update)
    const handleFormSubmit = (data: TransactionFormData) => {
        setIsSubmitting(true);

        if (editingTransaction) {
            // Update existing transaction
            router.put(
                '/transactions/' + editingTransaction.id, data,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast({
                            title: 'Success',
                            description: 'Transaction updated successfully',
                        });
                        setShowFormModal(false);
                        setEditingTransaction(null);
                    },
                    onError: (errors) => {
                        const errorMessage = Object.values(errors)
                            .flat()
                            .join(', ');
                        toast({
                            title: 'Error',
                            description:
                                errorMessage || 'Failed to update transaction',
                            variant: 'destructive',
                        });
                    },
                    onFinish: () => {
                        setIsSubmitting(false);
                    },
                }
            );
        } else {
            // Create new transaction
            router.post('/transactions', data, {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'Transaction created successfully',
                    });
                    setShowFormModal(false);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors)
                        .flat()
                        .join(', ');
                    toast({
                        title: 'Error',
                        description:
                            errorMessage || 'Failed to create transaction',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    // Handle delete - open confirmation dialog
    const handleDelete = (transactionId: number) => {
        const transaction = transactions.data.find(
            (t) => t.id === transactionId
        );
        if (transaction) {
            setDeletingTransaction(transaction);
            setShowDeleteDialog(true);
        }
    };

    // Confirm single delete
    const confirmDelete = () => {
        if (!deletingTransaction) return;

        setIsDeleting(true);
        router.delete('/transactions/' + deletingTransaction.id, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Transaction deleted successfully',
                });
                setShowDeleteDialog(false);
                setDeletingTransaction(null);
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete transaction',
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    // Handle bulk delete - open confirmation dialog
    const handleBulkDelete = () => {
        if (selectedTransactions.length === 0) {
            toast({
                title: 'Warning',
                description: 'Please select transactions to delete',
                variant: 'destructive',
            });
            return;
        }
        setShowBulkDeleteDialog(true);
    };

    // Confirm bulk delete
    const confirmBulkDelete = () => {
        setIsDeleting(true);
        router.post(
            '/transactions/bulk-destroy',
            { transaction_ids: selectedTransactions },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedTransactions([]);
                    toast({
                        title: 'Success',
                        description: 'Transactions deleted successfully',
                    });
                    setShowBulkDeleteDialog(false);
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to delete transactions',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsDeleting(false);
                },
            }
        );
    };

    // Handle view - navigate to show page
    const handleView = (transactionId: number) => {
        router.get('/transactions/' + transactionId);
    };

    // Handle refresh
    const handleRefresh = () => {
        setLoading(true);
        router.reload({
            preserveUrl: true,
            onFinish: () => setLoading(false),
        });
    };

    // Calculate net amount
    const netAmount = summary.total_income - summary.total_expenses;

    return (
        <>
            <Head title="Transactions" />

            <SidebarProvider>
                <AppSidebar variant="inset" />
                <SidebarInset>
                    <SiteHeader />
                    <div className="flex flex-1 flex-col">
                        <div className="@container/main flex flex-1 flex-col gap-2">
                            <div className="flex flex-col gap-4 py-4 md:gap-6 md:py-6 px-4 lg:px-6">
                                {/* Header Section */}
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h1 className="text-3xl font-bold">
                                            Transactions
                                        </h1>
                                        <p className="text-muted-foreground">
                                            Manage your income, expenses, and
                                            transfers
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRefresh}
                                            disabled={loading}
                                        >
                                            <RefreshCwIcon
                                                className={
                                                    loading
                                                        ? 'animate-spin'
                                                        : ''
                                                }
                                            />
                                            Refresh
                                        </Button>
                                        {selectedTransactions.length > 0 && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleBulkDelete}
                                            >
                                                Delete Selected (
                                                {selectedTransactions.length})
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            onClick={handleCreate}
                                        >
                                            <PlusIcon />
                                            Add Transaction
                                        </Button>
                                    </div>
                                </div>

                                {/* Summary Cards */}
                                <div className="grid gap-4 md:grid-cols-3">
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Total Income
                                            </CardTitle>
                                            <TrendingUpIcon className="h-4 w-4 text-green-600" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-green-600">
                                                {formatCurrency(
                                                    summary.total_income
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                From{' '}
                                                {
                                                    transactions.data.filter(
                                                        (t) =>
                                                            t.type === 'income'
                                                    ).length
                                                }{' '}
                                                transactions
                                            </p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Total Expenses
                                            </CardTitle>
                                            <TrendingDownIcon className="h-4 w-4 text-red-600" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-red-600">
                                                {formatCurrency(
                                                    summary.total_expenses
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                From{' '}
                                                {
                                                    transactions.data.filter(
                                                        (t) =>
                                                            t.type === 'expense'
                                                    ).length
                                                }{' '}
                                                transactions
                                            </p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Net Amount
                                            </CardTitle>
                                            <WalletIcon className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div
                                                className={`text-2xl font-bold ${
                                                    netAmount >= 0
                                                        ? 'text-green-600'
                                                        : 'text-red-600'
                                                }`}
                                            >
                                                {formatCurrency(netAmount)}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {summary.total_transactions}{' '}
                                                total transactions
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Data Table */}
                                <DataTableTransactions
                                    data={transactions.data}
                                    pagination={{
                                        current_page:
                                            transactions.current_page,
                                        per_page: transactions.per_page,
                                        total: transactions.total,
                                        last_page: transactions.last_page,
                                        from: transactions.from,
                                        to: transactions.to,
                                    }}
                                    filters={filters}
                                    accounts={accounts}
                                    categories={categories}
                                    selectedTransactions={selectedTransactions}
                                    onSearch={handleSearch}
                                    onPageChange={handlePageChange}
                                    onSort={handleSort}
                                    onFilterChange={handleFilterChange}
                                    onDelete={handleDelete}
                                    onEdit={handleEdit}
                                    onView={handleView}
                                    onSelectionChange={setSelectedTransactions}
                                />
                            </div>
                        </div>
                    </div>
                </SidebarInset>
            </SidebarProvider>

            {/* Transaction Form Modal (Add/Edit) */}
            <TransactionFormModal
                open={showFormModal}
                onOpenChange={(open) => {
                    setShowFormModal(open);
                    if (!open) {
                        setEditingTransaction(null);
                    }
                }}
                transaction={editingTransaction}
                accounts={accounts}
                categories={categories}
                onSubmit={handleFormSubmit}
                isSubmitting={isSubmitting}
            />

            {/* Delete Confirmation Dialog */}
            <DeleteTransactionDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                transaction={deletingTransaction}
                onConfirm={confirmDelete}
                isDeleting={isDeleting}
            />

            {/* Bulk Delete Confirmation Dialog */}
            <BulkDeleteTransactionDialog
                open={showBulkDeleteDialog}
                onOpenChange={setShowBulkDeleteDialog}
                count={selectedTransactions.length}
                onConfirm={confirmBulkDelete}
                isDeleting={isDeleting}
            />
        </>
    );
}
