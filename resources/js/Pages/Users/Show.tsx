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
    MailIcon,
    GlobeIcon,
    ClockIcon,
    LanguagesIcon,
    CalendarIcon,
    UserIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import { format } from 'date-fns';
import { useToast } from '@/hooks/use-toast';
import { useState } from 'react';
import { DeleteUserDialog } from '@/components/delete-user-dialog';

interface User {
    id: number;
    name: string;
    email: string;
    currency: string;
    timezone: string;
    language: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    avatar?: string;
}

interface PageProps {
    user: User;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: any;
}

export default function Show({ user, flash }: PageProps) {
    const { toast } = useToast();
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

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

    const handleEdit = () => {
        router.get('/users/' + user.id + '/edit');
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete('/users/' + user.id, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'User deleted successfully',
                });
                router.get('/users');
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete user',
                    variant: 'destructive',
                });
                setIsDeleting(false);
            },
            onFinish: () => {
                setShowDeleteDialog(false);
            },
        });
    };

    const formatDate = (dateString: string) => {
        return format(new Date(dateString), 'MMMM dd, yyyy \'at\' hh:mm a');
    };

    const getLanguageLabel = (code: string) => {
        const languages: Record<string, string> = {
            en: 'English',
            fil: 'Filipino',
            es: 'Spanish',
            zh: 'Chinese',
            ja: 'Japanese',
            ko: 'Korean',
            fr: 'French',
            de: 'German',
        };
        return languages[code] || code;
    };

    const getCurrencyLabel = (code: string) => {
        const currencies: Record<string, string> = {
            PHP: 'Philippine Peso',
            USD: 'US Dollar',
            EUR: 'Euro',
            GBP: 'British Pound',
            JPY: 'Japanese Yen',
            SGD: 'Singapore Dollar',
            AUD: 'Australian Dollar',
            CAD: 'Canadian Dollar',
            CNY: 'Chinese Yuan',
            KRW: 'Korean Won',
        };
        return currencies[code] || code;
    };

    return (
        <>
            <Head title={`User: ${user.name}`} />

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
                                        <Link href={'/users'}>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                            >
                                                <ArrowLeftIcon className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <div>
                                            <h1 className="text-3xl font-bold">
                                                User Details
                                            </h1>
                                            <p className="text-muted-foreground">
                                                View user information and
                                                settings
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={handleEdit}
                                        >
                                            <PencilIcon className="mr-2 h-4 w-4" />
                                            Edit
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={() =>
                                                setShowDeleteDialog(true)
                                            }
                                        >
                                            <TrashIcon className="mr-2 h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>

                                {/* User Profile Card */}
                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Basic Information */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <UserIcon className="h-5 w-5" />
                                                Basic Information
                                            </CardTitle>
                                            <CardDescription>
                                                User's personal details
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {/* Avatar and Name */}
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-2xl font-bold text-primary">
                                                    {user.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </div>
                                                <div>
                                                    <h3 className="text-xl font-semibold">
                                                        {user.name}
                                                    </h3>
                                                    <p className="text-muted-foreground">
                                                        ID: #{user.id}
                                                    </p>
                                                </div>
                                            </div>

                                            <Separator />

                                            {/* Email */}
                                            <div className="flex items-start gap-3">
                                                <MailIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium">
                                                        Email Address
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {user.email}
                                                    </p>
                                                </div>
                                                {user.email_verified_at ? (
                                                    <Badge
                                                        variant="default"
                                                        className="bg-green-500"
                                                    >
                                                        <ShieldCheckIcon className="mr-1 h-3 w-3" />
                                                        Verified
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Unverified
                                                    </Badge>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {/* Preferences */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <GlobeIcon className="h-5 w-5" />
                                                Preferences
                                            </CardTitle>
                                            <CardDescription>
                                                User's locale and regional
                                                settings
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {/* Currency */}
                                            <div className="flex items-start gap-3">
                                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-sm font-bold">
                                                    {user.currency}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        Currency
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {getCurrencyLabel(
                                                            user.currency
                                                        )}{' '}
                                                        ({user.currency})
                                                    </p>
                                                </div>
                                            </div>

                                            <Separator />

                                            {/* Timezone */}
                                            <div className="flex items-start gap-3">
                                                <ClockIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        Timezone
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {user.timezone}
                                                    </p>
                                                </div>
                                            </div>

                                            <Separator />

                                            {/* Language */}
                                            <div className="flex items-start gap-3">
                                                <LanguagesIcon className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        Language
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {getLanguageLabel(
                                                            user.language
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Activity Information */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <CalendarIcon className="h-5 w-5" />
                                            Activity Information
                                        </CardTitle>
                                        <CardDescription>
                                            Account creation and modification
                                            timestamps
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <div className="rounded-lg border p-4">
                                                <p className="text-sm text-muted-foreground">
                                                    Account Created
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {formatDate(user.created_at)}
                                                </p>
                                            </div>
                                            <div className="rounded-lg border p-4">
                                                <p className="text-sm text-muted-foreground">
                                                    Last Updated
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {formatDate(user.updated_at)}
                                                </p>
                                            </div>
                                            {user.email_verified_at && (
                                                <div className="rounded-lg border p-4">
                                                    <p className="text-sm text-muted-foreground">
                                                        Email Verified
                                                    </p>
                                                    <p className="mt-1 font-medium">
                                                        {formatDate(
                                                            user.email_verified_at
                                                        )}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </SidebarInset>
            </SidebarProvider>

            {/* Delete Confirmation Dialog */}
            <DeleteUserDialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
                user={user}
                onConfirm={handleDelete}
                isDeleting={isDeleting}
            />
        </>
    );
}
