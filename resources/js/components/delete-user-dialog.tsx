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

interface User {
    id: number;
    name: string;
    email: string;
}

interface DeleteUserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
    onConfirm: () => void;
    isDeleting?: boolean;
}

export function DeleteUserDialog({
    open,
    onOpenChange,
    user,
    onConfirm,
    isDeleting = false,
}: DeleteUserDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <AlertTriangleIcon className="h-5 w-5 text-destructive" />
                        </div>
                        <AlertDialogTitle>Delete User</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2">
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-foreground">
                            {user?.name}
                        </span>
                        ? This action cannot be undone.
                        <br />
                        <br />
                        <span className="text-muted-foreground">
                            Email: {user?.email}
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
                        Delete User
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

interface BulkDeleteDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    count: number;
    onConfirm: () => void;
    isDeleting?: boolean;
}

export function BulkDeleteDialog({
    open,
    onOpenChange,
    count,
    onConfirm,
    isDeleting = false,
}: BulkDeleteDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <AlertTriangleIcon className="h-5 w-5 text-destructive" />
                        </div>
                        <AlertDialogTitle>Delete Multiple Users</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2">
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-foreground">
                            {count} user{count !== 1 ? 's' : ''}
                        </span>
                        ? This action cannot be undone and will remove all
                        associated data.
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
                        Delete {count} User{count !== 1 ? 's' : ''}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
