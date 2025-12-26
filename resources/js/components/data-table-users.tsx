import { useState, useEffect } from 'react';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    useReactTable,
    SortingState,
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import {
    MoreVerticalIcon,
    SearchIcon,
    EditIcon,
    TrashIcon,
    EyeIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ArrowUpDownIcon,
} from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    currency: string;
    timezone: string;
    language: string;
    created_at: string;
    updated_at: string;
}

interface PaginationData {
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
}

interface DataTableProps {
    data: User[];
    pagination: PaginationData;
    filters: Filters;
    selectedUsers: number[];
    onSearch: (query: string) => void;
    onPageChange: (page: number) => void;
    onSort: (sortBy: string) => void;
    onDelete: (userId: number) => void;
    onEdit: (userId: number) => void;
    onView: (userId: number) => void;
    onSelectionChange: (selectedIds: number[]) => void;
}

export function DataTable({
    data,
    pagination,
    filters,
    selectedUsers,
    onSearch,
    onPageChange,
    onSort,
    onDelete,
    onEdit,
    onView,
    onSelectionChange,
}: DataTableProps) {
    const [searchValue, setSearchValue] = useState(filters.search || '');
    const [sorting, setSorting] = useState<SortingState>([]);

    useEffect(() => {
        setSearchValue(filters.search || '');
    }, [filters.search]);

    const columns: ColumnDef<User>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <div className="flex items-center justify-center">
                    <Checkbox
                        checked={
                            table.getIsAllPageRowsSelected() ||
                            (table.getIsSomePageRowsSelected() &&
                                'indeterminate')
                        }
                        onCheckedChange={(value) => {
                            table.toggleAllPageRowsSelected(!!value);
                            if (value) {
                                onSelectionChange(data.map((user) => user.id));
                            } else {
                                onSelectionChange([]);
                            }
                        }}
                        aria-label="Select all"
                    />
                </div>
            ),
            cell: ({ row }) => (
                <div className="flex items-center justify-center">
                    <Checkbox
                        checked={selectedUsers.includes(row.original.id)}
                        onCheckedChange={(value) => {
                            if (value) {
                                onSelectionChange([
                                    ...selectedUsers,
                                    row.original.id,
                                ]);
                            } else {
                                onSelectionChange(
                                    selectedUsers.filter(
                                        (id) => id !== row.original.id
                                    )
                                );
                            }
                        }}
                        aria-label="Select row"
                    />
                </div>
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('id')}
                    className="h-8 px-2"
                >
                    ID
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <div className="font-medium">#{row.original.id}</div>
            ),
        },
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('name')}
                    className="h-8 px-2"
                >
                    Name
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.original.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.original.email}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'currency',
            header: 'Currency',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-xs">
                    {row.original.currency}
                </Badge>
            ),
        },
        {
            accessorKey: 'timezone',
            header: 'Timezone',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.timezone}
                </span>
            ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => onSort('created_at')}
                    className="h-8 px-2"
                >
                    Created At
                    <ArrowUpDownIcon className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="text-sm">
                    {new Date(row.original.created_at).toLocaleDateString()}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                            <MoreVerticalIcon className="h-4 w-4" />
                            <span className="sr-only">Open menu</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onView(row.original.id)}>
                            <EyeIcon className="mr-2 h-4 w-4" />
                            View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onEdit(row.original.id)}>
                            <EditIcon className="mr-2 h-4 w-4" />
                            Edit User
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-destructive"
                            onClick={() => onDelete(row.original.id)}
                        >
                            <TrashIcon className="mr-2 h-4 w-4" />
                            Delete User
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
        state: {
            sorting,
        },
        onSortingChange: setSorting,
        manualSorting: true,
        manualPagination: true,
    });

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSearch(searchValue);
    };

    return (
        <div className="space-y-4">
            {/* Search Bar */}
            <form onSubmit={handleSearchSubmit} className="flex gap-2">
                <div className="relative flex-1 max-w-sm">
                    <SearchIcon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search users by name or email..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        className="pl-9"
                    />
                </div>
                <Button type="submit">Search</Button>
            </form>

            {/* Table */}
            <div className="rounded-lg border">
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
                                <TableRow key={row.id}>
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
                                    No users found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                    Showing {pagination.from || 0} to {pagination.to || 0} of{' '}
                    {pagination.total} users
                </div>
                <div className="flex gap-2">
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
                        {Array.from(
                            { length: pagination.last_page },
                            (_, i) => i + 1
                        )
                            .filter(
                                (page) =>
                                    page === 1 ||
                                    page === pagination.last_page ||
                                    Math.abs(page - pagination.current_page) <= 1
                            )
                            .map((page, index, array) => (
                                <>
                                    {index > 0 && array[index - 1] !== page - 1 && (
                                        <span
                                            key={`ellipsis-${page}`}
                                            className="px-2"
                                        >
                                            ...
                                        </span>
                                    )}
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
                                </>
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
