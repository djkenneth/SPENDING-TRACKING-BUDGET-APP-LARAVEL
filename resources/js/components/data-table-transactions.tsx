// resources/js/components/data-table-transactions.tsx

import * as React from 'react';
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    type ColumnDef,
} from '@tanstack/react-table';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ArrowUpDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    MoreHorizontalIcon,
    SearchIcon,
    EyeIcon,
    PencilIcon,
    TrashIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ArrowRightLeftIcon,
    FilterIcon,
    XIcon,
} from 'lucide-react';
import { format } from 'date-fns';

interface Transaction {
    id: number;
    description: string;
    amount: number;
    type: 'income' | 'expense' | 'transfer';
    date: string;
    is_cleared: boolean;
    is_recurring: boolean;
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

interface Account {
    id: number;
    name: string;
    type: string;
}

interface Category {
    id: number;
    name: string;
    type: string;
}

interface Pagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
}

interface Filters {
    search?: string;
    sort_by?: string;
    sort_order?: string;
    type?: string;
    account_id?: number;
    category_id?: number;
    start_date?: string;
    end_date?: string;
}

interface DataTableProps {
    data: Transaction[];
    pagination: Pagination;
    filters: Filters;
    accounts: Account[];
    categories: Category[];
    selectedTransactions: number[];
    onSearch: (query: string) => void;
    onPageChange: (page: number) => void;
    onSort: (sortBy: string) => void;
    onFilterChange: (filters: Partial<Filters>) => void;
    onDelete: (id: number) => void;
    onEdit: (id: number) => void;
    onView: (id: number) => void;
    onSelectionChange: (ids: number[]) => void;
}

export function DataTableTransactions({
    data,
    pagination,
    filters,
    accounts,
    categories,
    selectedTransactions,
    onSearch,
    onPageChange,
    onSort,
    onFilterChange,
    onDelete,
    onEdit,
    onView,
    onSelectionChange,
}: DataTableProps) {
    const [searchInput, setSearchInput] = React.useState(filters.search || '');
    const [showFilters, setShowFilters] = React.useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'income':
                return <ArrowUpIcon className="h-4 w-4 text-green-600" />;
            case 'expense':
                return <ArrowDownIcon className="h-4 w-4 text-red-600" />;
            case 'transfer':
                return <ArrowRightLeftIcon className="h-4 w-4 text-blue-600" />;
            default:
                return null;
        }
    };

    const getTypeBadgeVariant = (type: string) => {
        switch (type) {
            case 'income':
                return 'default';
            case 'expense':
                return 'destructive';
            case 'transfer':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    const columns: ColumnDef<Transaction>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={
                        data.length > 0 &&
                        selectedTransactions.length === data.length
                    }
                    onCheckedChange={(checked) => {
                        onSelectionChange(
                            checked ? data.map((t) => t.id) : []
                        );
                    }}
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={selectedTransactions.includes(row.original.id)}
                    onCheckedChange={(checked) => {
                        if (checked) {
                            onSelectionChange([
                                ...selectedTransactions,
                                row.original.id,
                            ]);
                        } else {
                            onSelectionChange(
                                selectedTransactions.filter(
                                    (id) => id !== row.original.id
                                )
                            );
                        }
                    }}
                    aria-label="Select row"
                />
            ),
            enableSorting: false,
        },
        {
            accessorKey: 'date',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('date')}
                    className="h-8 px-2"
                >
                    Date
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <div className="text-sm">
                    {format(new Date(row.original.date), 'MMM dd, yyyy')}
                </div>
            ),
        },
        {
            accessorKey: 'description',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('description')}
                    className="h-8 px-2"
                >
                    Description
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-medium">
                        {row.original.description}
                    </span>
                    {row.original.is_recurring && (
                        <Badge variant="outline" className="w-fit text-xs mt-1">
                            Recurring
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    {getTypeIcon(row.original.type)}
                    <Badge variant={getTypeBadgeVariant(row.original.type)}>
                        {row.original.type.charAt(0).toUpperCase() +
                            row.original.type.slice(1)}
                    </Badge>
                </div>
            ),
        },
        {
            accessorKey: 'category',
            header: 'Category',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    {row.original.category?.color && (
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: row.original.category.color,
                            }}
                        />
                    )}
                    <span className="text-sm">
                        {row.original.category?.name || '-'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'account',
            header: 'Account',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.account?.name || '-'}
                </span>
            ),
        },
        {
            accessorKey: 'amount',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('amount')}
                    className="h-8 px-2"
                >
                    Amount
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => {
                const amount = row.original.amount;
                const type = row.original.type;
                const colorClass =
                    type === 'income'
                        ? 'text-green-600'
                        : type === 'expense'
                        ? 'text-red-600'
                        : 'text-blue-600';
                const prefix =
                    type === 'income' ? '+' : type === 'expense' ? '-' : '';

                return (
                    <span className={`font-medium ${colorClass}`}>
                        {prefix}
                        {formatCurrency(amount)}
                    </span>
                );
            },
        },
        {
            accessorKey: 'is_cleared',
            header: 'Status',
            cell: ({ row }) => (
                <Badge
                    variant={row.original.is_cleared ? 'default' : 'secondary'}
                >
                    {row.original.is_cleared ? 'Cleared' : 'Pending'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                            <MoreHorizontalIcon className="h-4 w-4" />
                            <span className="sr-only">Open menu</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => onView(row.original.id)}
                        >
                            <EyeIcon className="mr-2 h-4 w-4" />
                            View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => onEdit(row.original.id)}
                        >
                            <PencilIcon className="mr-2 h-4 w-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => onDelete(row.original.id)}
                            className="text-destructive focus:text-destructive"
                        >
                            <TrashIcon className="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        onSearch(searchInput);
    };

    const clearFilters = () => {
        onFilterChange({
            type: undefined,
            account_id: undefined,
            category_id: undefined,
            start_date: undefined,
            end_date: undefined,
        });
    };

    const hasActiveFilters =
        filters.type ||
        filters.account_id ||
        filters.category_id ||
        filters.start_date ||
        filters.end_date;

    // Generate page numbers
    const getPageNumbers = () => {
        const pages: number[] = [];
        const maxVisible = 5;
        let start = Math.max(
            1,
            pagination.current_page - Math.floor(maxVisible / 2)
        );
        let end = Math.min(pagination.last_page, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    };

    return (
        <div className="space-y-4">
            {/* Search and Filter Bar */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <form onSubmit={handleSearch} className="flex gap-2">
                    <div className="relative">
                        <SearchIcon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search transactions..."
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            className="pl-9 w-[300px]"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <div className="flex gap-2">
                    <Button
                        variant={showFilters ? 'secondary' : 'outline'}
                        size="sm"
                        onClick={() => setShowFilters(!showFilters)}
                    >
                        <FilterIcon className="mr-2 h-4 w-4" />
                        Filters
                        {hasActiveFilters && (
                            <Badge
                                variant="destructive"
                                className="ml-2 h-5 w-5 rounded-full p-0 text-xs"
                            >
                                !
                            </Badge>
                        )}
                    </Button>
                    {hasActiveFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                        >
                            <XIcon className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    )}
                </div>
            </div>

            {/* Filter Panel */}
            {showFilters && (
                <div className="rounded-lg border p-4 space-y-4">
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Type</label>
                            <Select
                                value={filters.type || 'all'}
                                onValueChange={(value) =>
                                    onFilterChange({
                                        type: value === 'all' ? undefined : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="income">Income</SelectItem>
                                    <SelectItem value="expense">Expense</SelectItem>
                                    <SelectItem value="transfer">Transfer</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Account</label>
                            <Select
                                value={String(filters.account_id || 'all')}
                                onValueChange={(value) =>
                                    onFilterChange({
                                        account_id:
                                            value === 'all'
                                                ? undefined
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All accounts" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Accounts</SelectItem>
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
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Category</label>
                            <Select
                                value={String(filters.category_id || 'all')}
                                onValueChange={(value) =>
                                    onFilterChange({
                                        category_id:
                                            value === 'all'
                                                ? undefined
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Categories
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Start Date
                            </label>
                            <Input
                                type="date"
                                value={filters.start_date || ''}
                                onChange={(e) =>
                                    onFilterChange({
                                        start_date: e.target.value || undefined,
                                    })
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">End Date</label>
                            <Input
                                type="date"
                                value={filters.end_date || ''}
                                onChange={(e) =>
                                    onFilterChange({
                                        end_date: e.target.value || undefined,
                                    })
                                }
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef.header,
                                                  header.getContext()
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={
                                        selectedTransactions.includes(
                                            row.original.id
                                        ) && 'selected'
                                    }
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext()
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    No transactions found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                    {pagination.from && pagination.to ? (
                        <>
                            Showing {pagination.from} to {pagination.to} of{' '}
                            {pagination.total} transactions
                        </>
                    ) : (
                        'No transactions'
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onPageChange(pagination.current_page - 1)}
                        disabled={pagination.current_page === 1}
                    >
                        <ChevronLeftIcon className="h-4 w-4" />
                        Previous
                    </Button>
                    <div className="flex items-center gap-1">
                        {getPageNumbers().map((page) => (
                            <Button
                                key={page}
                                variant={
                                    pagination.current_page === page
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() => onPageChange(page)}
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onPageChange(pagination.current_page + 1)}
                        disabled={
                            pagination.current_page === pagination.last_page
                        }
                    >
                        Next
                        <ChevronRightIcon className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
