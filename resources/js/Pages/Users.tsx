import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { AppSidebar } from '@/components/app-sidebar';
import { SiteHeader } from '@/components/site-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { DataTable } from '@/components/data-table-users';
import { Button } from '@/components/ui/button';
import { PlusIcon, RefreshCwIcon } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    sort_by?: string;
    sort_order?: string;
    per_page?: number;
}

interface PageProps {
    users: PaginatedUsers;
    filters: Filters;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: any;
}

export default function Page() {
    const { users, filters, flash } = usePage<PageProps>().props;
    const { toast } = useToast();
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
    const [loading, setLoading] = useState(false);

    // Show flash messages
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

    // Handle search
    const handleSearch = (query: string) => {
        router.get(
            route('users.index'),
            { search: query, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle pagination
    const handlePageChange = (page: number) => {
        router.get(
            route('users.index'),
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
            route('users.index'),
            { ...filters, sort_by: sortBy, sort_order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle single delete
    const handleDelete = (userId: number) => {
        if (!confirm('Are you sure you want to delete this user?')) return;

        router.delete(route('users.destroy', userId), {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'User deleted successfully',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete user',
                    variant: 'destructive',
                });
            },
        });
    };

    // Handle bulk delete
    const handleBulkDelete = () => {
        if (selectedUsers.length === 0) {
            toast({
                title: 'Warning',
                description: 'Please select users to delete',
                variant: 'destructive',
            });
            return;
        }

        if (
            !confirm(
                `Are you sure you want to delete ${selectedUsers.length} user(s)?`
            )
        )
            return;

        router.post(
            route('users.bulk-destroy'),
            { user_ids: selectedUsers },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedUsers([]);
                    toast({
                        title: 'Success',
                        description: 'Users deleted successfully',
                    });
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to delete users',
                        variant: 'destructive',
                    });
                },
            }
        );
    };

    // Handle edit
    const handleEdit = (userId: number) => {
        router.get(route('users.edit', userId));
    };

    // Handle view
    const handleView = (userId: number) => {
        router.get(route('users.show', userId));
    };

    // Handle refresh
    const handleRefresh = () => {
        setLoading(true);
        router.reload({
            preserveUrl: true,
            onFinish: () => setLoading(false),
        });
    };

    // Handle create
    const handleCreate = () => {
        router.get(route('users.create'));
    };

    return (
        <>
            <Head title="Users Management" />

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
                                            Users Management
                                        </h1>
                                        <p className="text-muted-foreground">
                                            Manage all users in the system
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
                                                    loading ? 'animate-spin' : ''
                                                }
                                            />
                                            Refresh
                                        </Button>
                                        {selectedUsers.length > 0 && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleBulkDelete}
                                            >
                                                Delete Selected (
                                                {selectedUsers.length})
                                            </Button>
                                        )}
                                        <Button size="sm" onClick={handleCreate}>
                                            <PlusIcon />
                                            Add User
                                        </Button>
                                    </div>
                                </div>

                                {/* Data Table */}
                                <DataTable
                                    data={users.data}
                                    pagination={{
                                        current_page: users.current_page,
                                        per_page: users.per_page,
                                        total: users.total,
                                        last_page: users.last_page,
                                        from: users.from,
                                        to: users.to,
                                    }}
                                    filters={filters}
                                    selectedUsers={selectedUsers}
                                    onSearch={handleSearch}
                                    onPageChange={handlePageChange}
                                    onSort={handleSort}
                                    onDelete={handleDelete}
                                    onEdit={handleEdit}
                                    onView={handleView}
                                    onSelectionChange={setSelectedUsers}
                                />
                            </div>
                        </div>
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </>
    );
}
