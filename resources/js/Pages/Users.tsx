// resources/js/Pages/Users.tsx

import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { AppSidebar } from '@/components/app-sidebar';
import { SiteHeader } from '@/components/site-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { DataTable } from '@/components/data-table-users';
import { Button } from '@/components/ui/button';
import { PlusIcon, RefreshCwIcon } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { UserFormModal } from '@/components/user-form-modal';
import {
    DeleteUserDialog,
    BulkDeleteDialog,
} from '@/components/delete-user-dialog';

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

interface UserFormData {
    name: string;
    email: string;
    password?: string;
    password_confirmation?: string;
    currency: string;
    timezone: string;
    language: string;
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

    const [showFormModal, setShowFormModal] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showBulkDeleteDialog, setShowBulkDeleteDialog] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [deletingUser, setDeletingUser] = useState<User | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

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

    // Handle search
    const handleSearch = (query: string) => {
        router.get('/users',
            { search: query, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle pagination
    const handlePageChange = (page: number) => {
        router.get('/users',
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

        router.get('/users',
            { ...filters, sort_by: sortBy, sort_order: sortOrder },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle single delete
    const handleDelete = (userId: number) => {
        // if (!confirm('Are you sure you want to delete this user?')) return;

        // router.delete(route('users.destroy', userId), {
        //     preserveScroll: true,
        //     onSuccess: () => {
        //         toast({
        //             title: 'Success',
        //             description: 'User deleted successfully',
        //         });
        //     },
        //     onError: () => {
        //         toast({
        //             title: 'Error',
        //             description: 'Failed to delete user',
        //             variant: 'destructive',
        //         });
        //     },
        // });

        const user = users.data.find((u) => u.id === userId);
        if (user) {
            setDeletingUser(user);
            setShowDeleteDialog(true);
        }
    };

    // Handle bulk delete
    const handleBulkDelete = () => {
        // if (selectedUsers.length === 0) {
        //     toast({
        //         title: 'Warning',
        //         description: 'Please select users to delete',
        //         variant: 'destructive',
        //     });
        //     return;
        // }

        // if (
        //     !confirm(
        //         `Are you sure you want to delete ${selectedUsers.length} user(s)?`
        //     )
        // )
        //     return;

        // router.post(
        //     route('users.bulk-destroy'),
        //     { user_ids: selectedUsers },
        //     {
        //         preserveScroll: true,
        //         onSuccess: () => {
        //             setSelectedUsers([]);
        //             toast({
        //                 title: 'Success',
        //                 description: 'Users deleted successfully',
        //             });
        //         },
        //         onError: () => {
        //             toast({
        //                 title: 'Error',
        //                 description: 'Failed to delete users',
        //                 variant: 'destructive',
        //             });
        //         },
        //     }
        // );

        if (selectedUsers.length === 0) {
            toast({
                title: 'Warning',
                description: 'Please select users to delete',
                variant: 'destructive',
            });
            return;
        }
        setShowBulkDeleteDialog(true);
    };

    // Handle edit
    const handleEdit = (userId: number) => {
        // router.get(route('users.edit', userId));
        const user = users.data.find((u) => u.id === userId);
        if (user) {
            setEditingUser(user);
            setShowFormModal(true);
        }
    };

    // Handle view
    const handleView = (userId: number) => {
        router.get('/users/' + userId);
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
        // router.get(route('users.create'));
        setEditingUser(null);
        setShowFormModal(true);
    };

    // Handle form submit (create or update)
    const handleFormSubmit = (data: UserFormData) => {
        setIsSubmitting(true);

        if (editingUser) {
            // Update existing user
            router.put('/users/' + editingUser.id, data, {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'User updated successfully',
                    });
                    setShowFormModal(false);
                    setEditingUser(null);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat().join(', ');
                    toast({
                        title: 'Error',
                        description: errorMessage || 'Failed to update user',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        } else {
            // Create new user
            router.post('/users', data, {
                // preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'User created successfully',
                    });
                    setShowFormModal(false);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat().join(', ');
                    toast({
                        title: 'Error',
                        description: errorMessage || 'Failed to create user',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    // Confirm single delete
    const confirmDelete = () => {
        if (!deletingUser) return;

        setIsDeleting(true);
        router.delete('/users/' + deletingUser.id, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'User deleted successfully',
                });
                setShowDeleteDialog(false);
                setDeletingUser(null);
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete user',
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    // Confirm bulk delete
    const confirmBulkDelete = () => {
        setIsDeleting(true);
        router.post(
            '/users/bulk-destroy',
            { user_ids: selectedUsers },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedUsers([]);
                    toast({
                        title: 'Success',
                        description: 'Users deleted successfully',
                    });
                    setShowBulkDeleteDialog(false);
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to delete users',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsDeleting(false);
                },
            }
        );
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

                        {/* User Form Modal (Add/Edit) */}
            <UserFormModal
                open={showFormModal}
                onOpenChange={(open) => {
                    setShowFormModal(open);
                    if (!open) {
                        setEditingUser(null);
                    }
                }}
                user={editingUser}
                onSubmit={handleFormSubmit}
                isSubmitting={isSubmitting}
            />

            {/* Delete Confirmation Dialog */}
            <DeleteUserDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                user={deletingUser}
                onConfirm={confirmDelete}
                isDeleting={isDeleting}
            />

            {/* Bulk Delete Confirmation Dialog */}
            <BulkDeleteDialog
                open={showBulkDeleteDialog}
                onOpenChange={setShowBulkDeleteDialog}
                count={selectedUsers.length}
                onConfirm={confirmBulkDelete}
                isDeleting={isDeleting}
            />
        </>
    );
}
