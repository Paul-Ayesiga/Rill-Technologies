<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'vue-sonner';

// Define the notification type
interface Notification {
  id: string;
  type: string;
  data: {
    invoice_id?: string;
    invoice_path?: string;
    success?: boolean;
    message?: string;
  };
  read_at: string | null;
  created_at: string;
}

// State
const notifications = ref<Notification[]>([]);
const loading = ref(false);
const hasUnread = computed(() => notifications.value.length > 0);

// Fetch notifications
const fetchNotifications = async () => {
  loading.value = true;
  try {
    // Use Inertia's router to make the request
    const response = await fetch(route('notifications.unread'), {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    notifications.value = data.notifications || [];
    console.log('Fetched notifications:', data);
  } catch (error) {
    console.error('Error fetching notifications:', error);
  } finally {
    loading.value = false;
  }
};

// Mark notification as read
const markAsRead = async (notification: Notification) => {
  try {
    const response = await fetch(route('notifications.mark-read', notification.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Remove from the list
    notifications.value = notifications.value.filter((n: Notification) => n.id !== notification.id);

    // Handle invoice download if it's an invoice notification
    if (notification.type === 'App\\Notifications\\InvoiceReady' && notification.data.success && notification.data.invoice_path) {
      // Extract the invoice ID from the path
      // The path format is typically 'invoices/user_id/invoice_in_INVOICE_ID_timestamp.pdf'
      const pathParts = notification.data.invoice_path.split('/');
      const filename = pathParts[pathParts.length - 1];

      // Extract the invoice ID from the filename
      // Format: invoice_in_INVOICE_ID_timestamp.pdf
      // We need to extract just the INVOICE_ID part without the 'in_' prefix
      const match = filename.match(/invoice_in_([A-Za-z0-9]+)_/);
      let invoiceId = match ? match[1] : null;

      // Remove the 'in_' prefix if it exists (Stripe adds this)
      if (invoiceId && invoiceId.startsWith('in_')) {
        invoiceId = invoiceId.substring(3);
      }

      // Show a toast notification
      toast.success('Invoice Ready', {
        description: 'Your invoice is ready for download.',
        action: {
          label: 'Download',
          onClick: () => {
            if (invoiceId) {
              // Use the direct download route with the invoice ID
              window.open(route('invoice.download-direct', invoiceId), '_blank');
            } else {
              // Fallback to the file path download
              window.open(route('invoice.download', filename), '_blank');
            }
          }
        }
      });
    }
  } catch (error) {
    console.error('Error marking notification as read:', error);
    toast.error('Error', {
      description: 'Could not process the notification.'
    });
  }
};

// Mark all as read
const markAllAsRead = async () => {
  try {
    const response = await fetch(route('notifications.mark-all-read'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Clear the list
    notifications.value = [];

    // Show success toast
    toast.success('Notifications cleared', {
      description: 'All notifications have been marked as read'
    });
  } catch (error) {
    console.error('Error marking all notifications as read:', error);
    toast.error('Error', {
      description: 'Could not mark notifications as read.'
    });
  }
};

// Format notification time
const formatTime = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleString();
};

// Get notification icon based on type
const getNotificationIcon = (type: string) => {
  if (type === 'App\\Notifications\\InvoiceReady') {
    return 'file-text';
  }
  return 'bell';
};

// Set up Echo listener for real-time notifications
const setupEchoListener = () => {
  if (window.Echo) {
    // Get the authenticated user ID
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');

    if (userId) {
      // Listen for notifications on the private user channel
      window.Echo.private(`App.Models.User.${userId}`)
        .notification((notification: Notification) => {
          console.log('Received notification:', notification);

          // Add the notification to our list if it's not already there
          if (!notifications.value.some((n: Notification) => n.id === notification.id)) {
            notifications.value = [notification, ...notifications.value];
          }

          // Show a toast notification for invoice ready
          if (notification.type === 'App\\Notifications\\InvoiceReady' && notification.data.success) {
            // Extract the invoice ID from the path if available
            let invoiceId = null;
            if (notification.data.invoice_path) {
              const pathParts = notification.data.invoice_path.split('/');
              const filename = pathParts[pathParts.length - 1];
              const match = filename.match(/invoice_in_([A-Za-z0-9]+)_/);
              invoiceId = match ? match[1] : null;

              // Remove the 'in_' prefix if it exists
              if (invoiceId && invoiceId.startsWith('in_')) {
                invoiceId = invoiceId.substring(3);
              }
            }

            toast.success('Invoice Ready', {
              description: 'Your invoice has been generated and is ready for download.',
              action: {
                label: invoiceId ? 'Download' : 'View',
                onClick: () => {
                  if (invoiceId) {
                    // Open the invoice directly
                    window.open(route('invoice.download-direct', invoiceId), '_blank');
                    // Mark as read after opening
                    markAsRead(notification);
                  } else {
                    // Just mark as read and show in the dropdown
                    markAsRead(notification);
                  }
                }
              }
            });
          }
        });

      console.log(`Listening for notifications on App.Models.User.${userId}`);
    }
  } else {
    console.warn('Echo is not available. Real-time notifications will not work.');
  }
};

// Fetch notifications on mount
onMounted(() => {
  fetchNotifications();

  // Set up Echo listener
  setupEchoListener();

  // Set up polling as a fallback (every 30 seconds)
  const interval = setInterval(fetchNotifications, 30000);

  // Clean up on unmount
  return () => clearInterval(interval);
});
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="relative">
        <Bell class="h-5 w-5" />
        <span v-if="hasUnread" class="absolute top-0 right-0 h-2 w-2 rounded-full bg-red-500"></span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-80">
      <div class="flex items-center justify-between p-2">
        <h2 class="text-sm font-medium">Notifications</h2>
        <Button v-if="hasUnread" variant="ghost" size="sm" class="text-xs" @click="markAllAsRead">
          Mark all as read
        </Button>
      </div>
      <div v-if="loading" class="p-4 text-center text-sm text-muted-foreground">
        Loading notifications...
      </div>
      <div v-else-if="!hasUnread" class="p-4 text-center text-sm text-muted-foreground">
        No new notifications
      </div>
      <div v-else class="max-h-[300px] overflow-y-auto">
        <DropdownMenuItem v-for="notification in notifications" :key="notification.id"
          class="cursor-pointer flex flex-col items-start p-3 hover:bg-accent"
          @click="markAsRead(notification)">
          <div class="flex w-full">
            <div class="flex-1">
              <p class="text-sm font-medium">
                {{ notification.data.success ? 'Invoice Ready' : 'Invoice Generation Failed' }}
              </p>
              <p class="text-xs text-muted-foreground mt-1">{{ notification.data.message }}</p>
              <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.created_at) }}</p>
            </div>
          </div>
        </DropdownMenuItem>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
