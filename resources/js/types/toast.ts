export type ToastActionElement = React.ReactElement;

export interface ToastProps {
    id: string;
    title?: string;
    description?: string;
    action?: ToastActionElement;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    variant?: 'default' | 'destructive' | 'success';
}

export type ToasterToast = ToastProps & {
    id: string;
    title?: string;
    description?: string;
    action?: ToastActionElement;
};

export interface Toast extends ToasterToast {}

export interface ToastState {
    toasts: ToasterToast[];
}

export type ToastAction =
    | { type: 'ADD_TOAST'; toast: ToasterToast }
    | { type: 'UPDATE_TOAST'; toast: Partial<ToasterToast> }
    | { type: 'DISMISS_TOAST'; toastId?: string }
    | { type: 'REMOVE_TOAST'; toastId?: string };
