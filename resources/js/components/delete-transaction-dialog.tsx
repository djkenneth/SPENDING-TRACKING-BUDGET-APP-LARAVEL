// resources/js/components/delete-transaction-dialog.tsx

import * as React from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Loader2Icon, AlertTriangleIcon } from 'lucide-react';
import { format } from 'date-fns';

interface Transaction {
    id: number;
    description: string;
    amount: number;
    type: 'income' | 'expense' | 'transfer';
    date: string;
}

interface DeleteTransactionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction: Transaction | null;
    onConfirm: () => void;
    isDeleting?: boolean;
}

export function DeleteTransactionDialog({
    open,
    onOpenChange,
    transaction,
    onConfirm,
    isDeleting = false,
}: DeleteTransactionDialogProps) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'income':
                return 'text-green-600';
            case 'expense':
                return 'text-red-600';
            case 'transfer':
                return 'text-blue-600';
            default:
                return '';
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <AlertTriangleIcon className="h-5 w-5 text-destructive" />
                        </div>
                        <AlertDialogTitle>Delete Transaction</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2">
                        Are you sure you want to delete this transaction? This
                        action cannot be undone and will affect your account
                        balance.
                        <br />
                        <br />
                        <span className="block rounded-lg border p-3 text-foreground">
                            <span className="font-semibold">
                                {transaction?.description}
                            </span>
                            <br />
                            <span className={getTypeColor(transaction?.type || '')}>
                                {transaction?.type?.charAt(0).toUpperCase()}
                                {transaction?.type?.slice(1)}
                            </span>
                            {' • '}
                            <span className="font-medium">
                                {formatCurrency(transaction?.amount || 0)}
                            </span>
                            {' • '}
                            <span className="text-muted-foreground">
                                {transaction?.date &&
                                    format(new Date(transaction.date), 'MMM dd, yyyy')}
                            </span>
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                        disabled={isDeleting}
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        {isDeleting && (
                            <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        Delete Transaction
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

interface BulkDeleteTransactionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    count: number;
    onConfirm: () => void;
    isDeleting?: boolean;
}

export function BulkDeleteTransactionDialog({
    open,
    onOpenChange,
    count,
    onConfirm,
    isDeleting = false,
}: BulkDeleteTransactionDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <AlertTriangleIcon className="h-5 w-5 text-destructive" />
                        </div>
                        <AlertDialogTitle>
                            Delete Multiple Transactions
                        </AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2">
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-foreground">
                            {count} transaction{count !== 1 ? 's' : ''}
                        </span>
                        ? This action cannot be undone and will affect your
                        account balances.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                        disabled={isDeleting}
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        {isDeleting && (
                            <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        Delete {count} Transaction{count !== 1 ? 's' : ''}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
