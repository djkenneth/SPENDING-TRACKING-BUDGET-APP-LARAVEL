// resources/js/components/transaction-form-modal.tsx

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2Icon } from 'lucide-react';

// Validation schema for transaction form
const transactionFormSchema = z.object({
    account_id: z.coerce.number().min(1, 'Account is required'),
    category_id: z.coerce.number().min(1, 'Category is required'),
    transfer_account_id: z.coerce.number().optional().nullable(),
    description: z.string().min(1, 'Description is required').max(255),
    amount: z.coerce.number().positive('Amount must be greater than 0'),
    type: z.enum(['income', 'expense', 'transfer']),
    date: z.string().min(1, 'Date is required'),
    notes: z.string().max(1000).optional().nullable(),
    reference_number: z.string().max(100).optional().nullable(),
    location: z.string().max(255).optional().nullable(),
    is_recurring: z.boolean().default(false),
    recurring_type: z.enum(['weekly', 'monthly', 'quarterly', 'yearly']).optional().nullable(),
    recurring_interval: z.coerce.number().min(1).optional().nullable(),
    recurring_end_date: z.string().optional().nullable(),
    is_cleared: z.boolean().default(true),
});

type TransactionFormData = z.infer<typeof transactionFormSchema>;

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
}

interface TransactionFormModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction | null;
    accounts: Account[];
    categories: Category[];
    onSubmit: (data: TransactionFormData) => void;
    isSubmitting?: boolean;
}

const transactionTypes = [
    { value: 'income', label: 'Income', color: 'text-green-600' },
    { value: 'expense', label: 'Expense', color: 'text-red-600' },
    { value: 'transfer', label: 'Transfer', color: 'text-blue-600' },
];

const recurringTypes = [
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

export function TransactionFormModal({
    open,
    onOpenChange,
    transaction,
    accounts,
    categories,
    onSubmit,
    isSubmitting = false,
}: TransactionFormModalProps) {
    const isEditMode = !!transaction;

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors },
    } = useForm<TransactionFormData>({
        resolver: zodResolver(transactionFormSchema),
        defaultValues: {
            account_id: 0,
            category_id: 0,
            transfer_account_id: null,
            description: '',
            amount: 0,
            type: 'expense',
            date: new Date().toISOString().split('T')[0],
            notes: '',
            reference_number: '',
            location: '',
            is_recurring: false,
            recurring_type: null,
            recurring_interval: 1,
            recurring_end_date: null,
            is_cleared: true,
        },
    });

    // Watch values for conditional rendering
    const type = watch('type');
    const isRecurring = watch('is_recurring');
    const accountId = watch('account_id');
    const categoryId = watch('category_id');
    const transferAccountId = watch('transfer_account_id');
    const isCleared = watch('is_cleared');

    // Filter categories based on transaction type
    const filteredCategories = React.useMemo(() => {
        if (type === 'transfer') {
            return categories.filter(c => c.type === 'transfer' || c.type === 'expense');
        }
        return categories.filter(c => c.type === type || c.type === 'transfer');
    }, [categories, type]);

    // Filter transfer accounts (exclude selected account)
    const transferAccounts = React.useMemo(() => {
        return accounts.filter(a => a.id !== Number(accountId));
    }, [accounts, accountId]);

    // Reset form when modal opens/closes or transaction changes
    React.useEffect(() => {
        if (open) {
            if (transaction) {
                reset({
                    account_id: transaction.account_id,
                    category_id: transaction.category_id,
                    transfer_account_id: transaction.transfer_account_id || null,
                    description: transaction.description,
                    amount: transaction.amount,
                    type: transaction.type,
                    date: transaction.date.split('T')[0],
                    notes: transaction.notes || '',
                    reference_number: transaction.reference_number || '',
                    location: transaction.location || '',
                    is_recurring: transaction.is_recurring,
                    recurring_type: transaction.recurring_type as any || null,
                    recurring_interval: transaction.recurring_interval || 1,
                    recurring_end_date: transaction.recurring_end_date?.split('T')[0] || null,
                    is_cleared: transaction.is_cleared,
                });
            } else {
                reset({
                    account_id: accounts[0]?.id || 0,
                    category_id: 0,
                    transfer_account_id: null,
                    description: '',
                    amount: 0,
                    type: 'expense',
                    date: new Date().toISOString().split('T')[0],
                    notes: '',
                    reference_number: '',
                    location: '',
                    is_recurring: false,
                    recurring_type: null,
                    recurring_interval: 1,
                    recurring_end_date: null,
                    is_cleared: true,
                });
            }
        }
    }, [open, transaction, accounts, reset]);

    const onFormSubmit = (data: TransactionFormData) => {
        // Clean up data
        const submitData = { ...data };
        if (!submitData.is_recurring) {
            submitData.recurring_type = null;
            submitData.recurring_interval = null;
            submitData.recurring_end_date = null;
        }
        if (submitData.type !== 'transfer') {
            submitData.transfer_account_id = null;
        }
        onSubmit(submitData);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isEditMode ? 'Edit Transaction' : 'Add New Transaction'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditMode
                            ? 'Update the transaction details.'
                            : 'Create a new transaction. Fields with * are required.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit(onFormSubmit)}
                    className="space-y-4"
                >
                    {/* Transaction Type */}
                    <div className="space-y-2">
                        <Label>
                            Type <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={type}
                            onValueChange={(value: 'income' | 'expense' | 'transfer') =>
                                setValue('type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {transactionTypes.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        <span className={t.color}>{t.label}</span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Amount and Date Row */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="amount">
                                Amount <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                {...register('amount')}
                                aria-invalid={!!errors.amount}
                            />
                            {errors.amount && (
                                <p className="text-sm text-destructive">
                                    {errors.amount.message}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="date">
                                Date <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="date"
                                type="date"
                                {...register('date')}
                                aria-invalid={!!errors.date}
                            />
                            {errors.date && (
                                <p className="text-sm text-destructive">
                                    {errors.date.message}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Account and Category Row */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>
                                Account <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={String(accountId)}
                                onValueChange={(value) =>
                                    setValue('account_id', Number(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select account" />
                                </SelectTrigger>
                                <SelectContent>
                                    {accounts.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={String(account.id)}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.account_id && (
                                <p className="text-sm text-destructive">
                                    {errors.account_id.message}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>
                                Category <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={String(categoryId)}
                                onValueChange={(value) =>
                                    setValue('category_id', Number(value))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredCategories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && (
                                <p className="text-sm text-destructive">
                                    {errors.category_id.message}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Transfer Account (only for transfer type) */}
                    {type === 'transfer' && (
                        <div className="space-y-2">
                            <Label>
                                Transfer To <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={String(transferAccountId || '')}
                                onValueChange={(value) =>
                                    setValue('transfer_account_id', value ? Number(value) : null)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select destination account" />
                                </SelectTrigger>
                                <SelectContent>
                                    {transferAccounts.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={String(account.id)}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">
                            Description <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="description"
                            placeholder="Enter transaction description"
                            {...register('description')}
                            aria-invalid={!!errors.description}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">
                                {errors.description.message}
                            </p>
                        )}
                    </div>

                    {/* Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            placeholder="Additional notes (optional)"
                            rows={2}
                            {...register('notes')}
                        />
                    </div>

                    {/* Reference and Location Row */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="reference_number">Reference Number</Label>
                            <Input
                                id="reference_number"
                                placeholder="e.g., Invoice #123"
                                {...register('reference_number')}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="location">Location</Label>
                            <Input
                                id="location"
                                placeholder="e.g., Store name"
                                {...register('location')}
                            />
                        </div>
                    </div>

                    {/* Recurring Transaction */}
                    <div className="space-y-4 rounded-lg border p-4">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="is_recurring"
                                checked={isRecurring}
                                onCheckedChange={(checked) =>
                                    setValue('is_recurring', !!checked)
                                }
                            />
                            <Label htmlFor="is_recurring" className="cursor-pointer">
                                Make this a recurring transaction
                            </Label>
                        </div>

                        {isRecurring && (
                            <div className="grid grid-cols-3 gap-4 pt-2">
                                <div className="space-y-2">
                                    <Label>Frequency</Label>
                                    <Select
                                        value={watch('recurring_type') || ''}
                                        onValueChange={(value) =>
                                            setValue('recurring_type', value as any)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {recurringTypes.map((rt) => (
                                                <SelectItem key={rt.value} value={rt.value}>
                                                    {rt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="recurring_interval">Interval</Label>
                                    <Input
                                        id="recurring_interval"
                                        type="number"
                                        min="1"
                                        {...register('recurring_interval')}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="recurring_end_date">End Date</Label>
                                    <Input
                                        id="recurring_end_date"
                                        type="date"
                                        {...register('recurring_end_date')}
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Cleared Status */}
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_cleared"
                            checked={isCleared}
                            onCheckedChange={(checked) =>
                                setValue('is_cleared', !!checked)
                            }
                        />
                        <Label htmlFor="is_cleared" className="cursor-pointer">
                            Transaction is cleared/confirmed
                        </Label>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting && (
                                <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            {isEditMode ? 'Update Transaction' : 'Create Transaction'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
