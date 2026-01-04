// resources/js/components/user-form-modal.tsx

import * as React from 'react';
import { useForm, type SubmitHandler } from 'react-hook-form';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Loader2Icon } from 'lucide-react';

// Base schema for user form
const baseUserSchema = z.object({
    name: z
        .string()
        .min(1, 'Name is required')
        .max(255, 'Name must be less than 255 characters'),
    email: z.string().email('Invalid email address').max(255),
    password: z.string().optional(),
    password_confirmation: z.string().optional(),
    currency: z.string().max(3).default('PHP'),
    timezone: z.string().max(50).default('Asia/Manila'),
    language: z.string().max(10).default('en'),
});

// Schema for edit mode (password optional)
const editUserSchema = baseUserSchema.refine(
    (data) => {
        if (data.password && data.password !== data.password_confirmation) {
            return false;
        }
        return true;
    },
    {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    }
);

// Schema for create mode (password required)
const createUserSchema = baseUserSchema
    .refine(
        (data) => {
            if (!data.password || data.password.length < 8) {
                return false;
            }
            return true;
        },
        {
            message: 'Password must be at least 8 characters',
            path: ['password'],
        }
    )
    .refine(
        (data) => {
            if (data.password && data.password !== data.password_confirmation) {
                return false;
            }
            return true;
        },
        {
            message: 'Passwords do not match',
            path: ['password_confirmation'],
        }
    );

// Form data type
type UserFormData = z.infer<typeof baseUserSchema>;

interface User {
    id: number;
    name: string;
    email: string;
    currency: string;
    timezone: string;
    language: string;
}

interface UserFormModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user?: User | null;
    onSubmit: (data: UserFormData) => void;
    isSubmitting?: boolean;
}

const timezones = [
    { value: 'Asia/Manila', label: 'Asia/Manila (PHT)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (SGT)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (JST)' },
    { value: 'Asia/Hong_Kong', label: 'Asia/Hong Kong (HKT)' },
    { value: 'America/New_York', label: 'America/New York (EST)' },
    { value: 'America/Los_Angeles', label: 'America/Los Angeles (PST)' },
    { value: 'America/Chicago', label: 'America/Chicago (CST)' },
    { value: 'Europe/London', label: 'Europe/London (GMT)' },
    { value: 'Europe/Paris', label: 'Europe/Paris (CET)' },
    { value: 'Australia/Sydney', label: 'Australia/Sydney (AEDT)' },
    { value: 'Pacific/Auckland', label: 'Pacific/Auckland (NZDT)' },
];

const currencies = [
    { value: 'PHP', label: 'PHP - Philippine Peso' },
    { value: 'USD', label: 'USD - US Dollar' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'GBP', label: 'GBP - British Pound' },
    { value: 'JPY', label: 'JPY - Japanese Yen' },
    { value: 'SGD', label: 'SGD - Singapore Dollar' },
    { value: 'AUD', label: 'AUD - Australian Dollar' },
    { value: 'CAD', label: 'CAD - Canadian Dollar' },
    { value: 'CNY', label: 'CNY - Chinese Yuan' },
    { value: 'KRW', label: 'KRW - Korean Won' },
];

const languages = [
    { value: 'en', label: 'English' },
    { value: 'fil', label: 'Filipino' },
    { value: 'es', label: 'Spanish' },
    { value: 'zh', label: 'Chinese' },
    { value: 'ja', label: 'Japanese' },
    { value: 'ko', label: 'Korean' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
];

export function UserFormModal({
    open,
    onOpenChange,
    user,
    onSubmit,
    isSubmitting = false,
}: UserFormModalProps) {
    const isEditMode = !!user;

    const schema = isEditMode ? editUserSchema : createUserSchema;

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors },
    } = useForm<UserFormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            currency: 'PHP',
            timezone: 'Asia/Manila',
            language: 'en',
        },
    });

    // Watch values for controlled selects
    const currency = watch('currency');
    const timezone = watch('timezone');
    const language = watch('language');

    // Reset form when modal opens/closes or user changes
    React.useEffect(() => {
        if (open) {
            if (user) {
                reset({
                    name: user.name,
                    email: user.email,
                    password: '',
                    password_confirmation: '',
                    currency: user.currency || 'PHP',
                    timezone: user.timezone || 'Asia/Manila',
                    language: user.language || 'en',
                });
            } else {
                reset({
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    currency: 'PHP',
                    timezone: 'Asia/Manila',
                    language: 'en',
                });
            }
        }
    }, [open, user, reset]);

    const onFormSubmit = (data: UserFormData) => {
        // Remove empty password fields for edit mode
        const submitData = { ...data };
        if (isEditMode && !submitData.password) {
            delete submitData.password;
            delete submitData.password_confirmation;
        }

        onSubmit(submitData);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEditMode ? 'Edit User' : 'Add New User'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditMode
                            ? 'Update user information. Leave password empty to keep current password.'
                            : 'Create a new user account. All fields with * are required.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit(onFormSubmit)}
                    className="space-y-4"
                >
                    {/* Name Field */}
                    <div className="space-y-2">
                        <Label htmlFor="name">
                            Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            placeholder="Enter full name"
                            {...register('name')}
                            aria-invalid={!!errors.name}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name.message}
                            </p>
                        )}
                    </div>

                    {/* Email Field */}
                    <div className="space-y-2">
                        <Label htmlFor="email">
                            Email <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            placeholder="Enter email address"
                            {...register('email')}
                            aria-invalid={!!errors.email}
                        />
                        {errors.email && (
                            <p className="text-sm text-destructive">
                                {errors.email.message}
                            </p>
                        )}
                    </div>

                    {/* Password Fields */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="password">
                                Password{' '}
                                {!isEditMode && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                placeholder={
                                    isEditMode
                                        ? 'Leave empty to keep'
                                        : 'Min 8 characters'
                                }
                                {...register('password')}
                                aria-invalid={!!errors.password}
                            />
                            {errors.password && (
                                <p className="text-sm text-destructive">
                                    {errors.password.message}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">
                                Confirm Password
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                placeholder="Confirm password"
                                {...register('password_confirmation')}
                                aria-invalid={!!errors.password_confirmation}
                            />
                            {errors.password_confirmation && (
                                <p className="text-sm text-destructive">
                                    {errors.password_confirmation.message}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Currency Select */}
                    <div className="space-y-2">
                        <Label htmlFor="currency">Currency</Label>
                        <Select
                            value={currency}
                            onValueChange={(value) => setValue('currency', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>
                            <SelectContent>
                                {currencies.map((c) => (
                                    <SelectItem key={c.value} value={c.value}>
                                        {c.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Timezone Select */}
                    <div className="space-y-2">
                        <Label htmlFor="timezone">Timezone</Label>
                        <Select
                            value={timezone}
                            onValueChange={(value) => setValue('timezone', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select timezone" />
                            </SelectTrigger>
                            <SelectContent>
                                {timezones.map((tz) => (
                                    <SelectItem key={tz.value} value={tz.value}>
                                        {tz.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Language Select */}
                    <div className="space-y-2">
                        <Label htmlFor="language">Language</Label>
                        <Select
                            value={language}
                            onValueChange={(value) => setValue('language', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select language" />
                            </SelectTrigger>
                            <SelectContent>
                                {languages.map((lang) => (
                                    <SelectItem key={lang.value} value={lang.value}>
                                        {lang.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                            {isEditMode ? 'Update User' : 'Create User'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
