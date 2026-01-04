// resources/js/Pages/Transactions/Show.tsx

import { Head, Link, router } from '@inertiajs/react';
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
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    ArrowLeftIcon,
    PencilIcon,
    TrashIcon,
    CalendarIcon,
    WalletIcon,
    TagIcon,
    MapPinIcon,
    FileTextIcon,
    RepeatIcon,
    CheckCircleIcon,
    ClockIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ArrowRightLeftIcon,
    HashIcon,
} from 'lucide-react';
import { format } from 'date-fns';
import { useToast } from '@/hooks/use-toast';
import { useState } from 'react';
import { DeleteTransactionDialog } from '@/components/delete-transaction-dialog';

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
    tags?: string[] | null;
    reference_number?: string | null;
    location?: string | null;
    is_recurring: boolean;
    recurring_type?: string | null;
    recurring_interval?: number | null;
    recurring_end_date?: string | null;
    is_cleared: boolean;
    cleared_at?: string | null;
    created_at: string;
    updated_at: string;
    account?: {
        id: number;
        name: string;
        type: string;
        color?: string;
        balance: number;
    };
    category?: {
        id: number;
        name: string;
        icon?: string;
        color?: string;
        type: string;
    };
    transferAccount?: {
        id: number;
        name: string;
        type: string;
        color?: string;
    };
}

interface PageProps {
    transaction: Transaction;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: any;
}

export default function Show({ transaction, flash }: PageProps) {
    const { toast } = useToast();
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // Show flash messages
    if (flash?.success) {
        toast({ title: 'Success', description: flash.success });
    }

    if (flash?.error) {
        toast({ title: 'Error', description: flash.error, variant: 'destructive' });
    }

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return format(new Date(dateString), "MMMM dd, yyyy 'at' hh:mm a");
    };

    const formatDateShort = (dateString: string) => {
        return format(new Date(dateString), 'MMM dd, yyyy');
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete('/transactions/' + transaction.id, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Success', description: 'Transaction deleted successfully' });
                router.get('/transactions');
            },
            onError: () => {
                toast({ title: 'Error', description: 'Failed to delete transaction', variant: 'destructive' });
                setIsDeleting(false);
            },
            onFinish: () => setShowDeleteDialog(false),
        });
    };

    const getTypeIcon = () => {
        switch (transaction.type) {
            case 'income':
                return <ArrowUpIcon className="h-6 w-6 text-green-600" />;
            case 'expense':
                return <ArrowDownIcon className="h-6 w-6 text-red-600" />;
            case 'transfer':
                return <ArrowRightLeftIcon className="h-6 w-6 text-blue-600" />;
        }
    };

    const getTypeColor = () => {
        switch (transaction.type) {
            case 'income':
                return 'text-green-600';
            case 'expense':
                return 'text-red-600';
            case 'transfer':
                return 'text-blue-600';
        }
    };

    const getTypeBadgeVariant = () => {
        switch (transaction.type) {
            case 'income':
                return 'default';
            case 'expense':
                return 'destructive';
            case 'transfer':
                return 'secondary';
        }
    };

    const getRecurringLabel = () => {
        if (!transaction.is_recurring || !transaction.recurring_type) return null;
        const interval = transaction.recurring_interval || 1;
        const type = transaction.recurring_type;
        if (interval === 1) {
            return type.charAt(0).toUpperCase() + type.slice(1);
        }
        return `Every ${interval} ${type}`;
    };

    return (
        <>
            <Head title={`Transaction: ${transaction.description}`} />

            <SidebarProvider>
                <AppSidebar variant="inset" />
                <SidebarInset>
                    <SiteHeader />
                    <div className="flex flex-1 flex-col">
                        <div className="@container/main flex flex-1 flex-col gap-2">
                            <div className="flex flex-col gap-4 py-4 md:gap-6 md:py-6 px-4 lg:px-6">
                                {/* Header with Back Button */}
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <Link href="/transactions">
                                            <Button variant="outline" size="icon">
                                                <ArrowLeftIcon className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <div>
                                            <h1 className="text-3xl font-bold">Transaction Details</h1>
                                            <p className="text-muted-foreground">View transaction information</p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="destructive" onClick={() => setShowDeleteDialog(true)}>
                                            <TrashIcon className="mr-2 h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>

                                {/* Transaction Header Card */}
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className={`flex h-16 w-16 items-center justify-center rounded-full ${
                                                    transaction.type === 'income' ? 'bg-green-100' :
                                                    transaction.type === 'expense' ? 'bg-red-100' : 'bg-blue-100'
                                                }`}>
                                                    {getTypeIcon()}
                                                </div>
                                                <div>
                                                    <h2 className="text-2xl font-bold">{transaction.description}</h2>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <Badge variant={getTypeBadgeVariant()}>
                                                            {transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1)}
                                                        </Badge>
                                                        {transaction.is_cleared ? (
                                                            <Badge variant="outline" className="text-green-600 border-green-600">
                                                                <CheckCircleIcon className="mr-1 h-3 w-3" />
                                                                Cleared
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-yellow-600 border-yellow-600">
                                                                <ClockIcon className="mr-1 h-3 w-3" />
                                                                Pending
                                                            </Badge>
                                                        )}
                                                        {transaction.is_recurring && (
                                                            <Badge variant="outline">
                                                                <RepeatIcon className="mr-1 h-3 w-3" />
                                                                Recurring
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className={`text-3xl font-bold ${getTypeColor()}`}>
                                                    {transaction.type === 'expense' ? '-' : transaction.type === 'income' ? '+' : ''}
                                                    {formatCurrency(transaction.amount)}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDateShort(transaction.date)}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Transaction Details */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <FileTextIcon className="h-5 w-5" />
                                                Transaction Details
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {/* Account */}
                                            <div className="flex items-start gap-3">
                                                <WalletIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">Account</p>
                                                    <p className="text-muted-foreground">
                                                        {transaction.account?.name || 'Unknown Account'}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Transfer Account */}
                                            {transaction.type === 'transfer' && transaction.transferAccount && (
                                                <>
                                                    <Separator />
                                                    <div className="flex items-start gap-3">
                                                        <ArrowRightLeftIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">Transfer To</p>
                                                            <p className="text-muted-foreground">
                                                                {transaction.transferAccount.name}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </>
                                            )}

                                            <Separator />

                                            {/* Category */}
                                            <div className="flex items-start gap-3">
                                                <TagIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">Category</p>
                                                    <div className="flex items-center gap-2">
                                                        {transaction.category?.color && (
                                                            <div
                                                                className="h-3 w-3 rounded-full"
                                                                style={{ backgroundColor: transaction.category.color }}
                                                            />
                                                        )}
                                                        <p className="text-muted-foreground">
                                                            {transaction.category?.name || 'Uncategorized'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <Separator />

                                            {/* Date */}
                                            <div className="flex items-start gap-3">
                                                <CalendarIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">Date</p>
                                                    <p className="text-muted-foreground">
                                                        {formatDateShort(transaction.date)}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Reference Number */}
                                            {transaction.reference_number && (
                                                <>
                                                    <Separator />
                                                    <div className="flex items-start gap-3">
                                                        <HashIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">Reference Number</p>
                                                            <p className="text-muted-foreground">
                                                                {transaction.reference_number}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </>
                                            )}

                                            {/* Location */}
                                            {transaction.location && (
                                                <>
                                                    <Separator />
                                                    <div className="flex items-start gap-3">
                                                        <MapPinIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">Location</p>
                                                            <p className="text-muted-foreground">
                                                                {transaction.location}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Additional Information */}
                                    <div className="space-y-6">
                                        {/* Recurring Info */}
                                        {transaction.is_recurring && (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle className="flex items-center gap-2">
                                                        <RepeatIcon className="h-5 w-5" />
                                                        Recurring Details
                                                    </CardTitle>
                                                </CardHeader>
                                                <CardContent className="space-y-4">
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div className="rounded-lg border p-3">
                                                            <p className="text-sm text-muted-foreground">Frequency</p>
                                                            <p className="font-medium">{getRecurringLabel()}</p>
                                                        </div>
                                                        {transaction.recurring_end_date && (
                                                            <div className="rounded-lg border p-3">
                                                                <p className="text-sm text-muted-foreground">End Date</p>
                                                                <p className="font-medium">
                                                                    {formatDateShort(transaction.recurring_end_date)}
                                                                </p>
                                                            </div>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        )}

                                        {/* Notes */}
                                        {transaction.notes && (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>Notes</CardTitle>
                                                </CardHeader>
                                                <CardContent>
                                                    <p className="text-muted-foreground whitespace-pre-wrap">
                                                        {transaction.notes}
                                                    </p>
                                                </CardContent>
                                            </Card>
                                        )}

                                        {/* Tags */}
                                        {transaction.tags && transaction.tags.length > 0 && (
                                            <Card>
                                                <CardHeader>
                                                    <CardTitle>Tags</CardTitle>
                                                </CardHeader>
                                                <CardContent>
                                                    <div className="flex flex-wrap gap-2">
                                                        {transaction.tags.map((tag, index) => (
                                                            <Badge key={index} variant="secondary">
                                                                {tag}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        )}

                                        {/* Activity Information */}
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="flex items-center gap-2">
                                                    <ClockIcon className="h-5 w-5" />
                                                    Activity
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="rounded-lg border p-3">
                                                        <p className="text-sm text-muted-foreground">Created</p>
                                                        <p className="font-medium text-sm">
                                                            {formatDate(transaction.created_at)}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border p-3">
                                                        <p className="text-sm text-muted-foreground">Last Updated</p>
                                                        <p className="font-medium text-sm">
                                                            {formatDate(transaction.updated_at)}
                                                        </p>
                                                    </div>
                                                    {transaction.cleared_at && (
                                                        <div className="rounded-lg border p-3 sm:col-span-2">
                                                            <p className="text-sm text-muted-foreground">Cleared At</p>
                                                            <p className="font-medium text-sm">
                                                                {formatDate(transaction.cleared_at)}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </SidebarInset>
            </SidebarProvider>

            {/* Delete Confirmation Dialog */}
            <DeleteTransactionDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                transaction={transaction}
                onConfirm={handleDelete}
                isDeleting={isDeleting}
            />
        </>
    );
}
