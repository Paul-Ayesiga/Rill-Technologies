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
  data?: {
    invoice_id?: string;
    invoice_path?: string;
    success?: boolean;
    message?: string;
    batch_id?: string;
    total_invoices?: number;
    successful_invoices?: number;
    failed_invoices?: number;
  };
  read_at: string | null;
  created_at: string;

  // Batch progress notification properties
  batch_id?: string;
  total_invoices?: number;
  processed_invoices?: number;
  progress?: number;
  message?: string;
  time?: string;

  // Batch complete notification properties
  success?: boolean;
  successful_invoices?: number;
  failed_invoices?: number;
}

// State
const notifications = ref<Notification[]>([]);
const loading = ref(false);
const hasUnread = computed(() => notifications.value.length > 0);

// Track the last fetch time to avoid excessive polling
const lastFetchTime = ref(0);
const FETCH_COOLDOWN = 10000; // 10 seconds cooldown between fetches

// Fetch notifications with optimizations to reduce unnecessary API calls
const fetchNotifications = async (force = false) => {
  // Skip if already loading
  if (loading.value && !force) return;

  // Skip if we fetched recently (within the cooldown period) unless forced
  const now = Date.now();
  if (!force && (now - lastFetchTime.value < FETCH_COOLDOWN)) {
    console.log('Skipping notification fetch - cooldown period active');
    return;
  }

  // Update the last fetch time
  lastFetchTime.value = now;
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

    // Only update if we have new notifications or if the count changed
    const newNotifications = data.notifications || [];
    const currentCount = notifications.value.length;
    const newCount = newNotifications.length;

    if (newCount !== currentCount || JSON.stringify(newNotifications) !== JSON.stringify(notifications.value)) {
      notifications.value = newNotifications;

      // Only log in non-production environments
      // We'll use a simple check instead of process.env to avoid TypeScript issues
      const isDevelopment = window.location.hostname === 'localhost' ||
                           window.location.hostname === '127.0.0.1';

      if (isDevelopment) {
        console.log('Fetched notifications:', data);

        // Log the types of notifications we received
        const notificationTypes = notifications.value.map(n => n.type);
        console.log('Notification types:', notificationTypes);

        // Check if we have any batch complete notifications
        const batchCompleteNotifications = notifications.value.filter(n =>
          n.type === 'App\\Notifications\\InvoiceBatchComplete'
        );

        if (batchCompleteNotifications.length > 0) {
          console.log('Found batch complete notifications:', batchCompleteNotifications);
        }
      }
    }
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

    // Handle different notification types
    if (notification.type === 'App\\Notifications\\InvoiceReady' && notification.data && notification.data.success && notification.data.invoice_path) {
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
    } else if (notification.type === 'App\\Notifications\\InvoiceBatchComplete') {
      console.log('Marking batch complete notification as read:', notification);

      // Show a toast notification for batch completion
      if (notification.success) {
        toast.success('Invoices Ready for Download', {
          description: `All ${notification.total_invoices} invoices have been generated successfully.`,
          action: {
            label: 'Download',
            onClick: () => {
              // Open the batch download route with the batch ID
              window.open(route('invoice.download-batch', notification.data?.batch_id || notification.batch_id), '_blank');
            }
          }
        });
      } else {
        toast.warning('Invoices Ready with Issues', {
          description: `${notification.successful_invoices || 0} of ${notification.total_invoices} invoices were generated successfully.`,
          action: {
            label: 'Download',
            onClick: () => {
              // Open the batch download route with the batch ID
              window.open(route('invoice.download-batch', notification.data?.batch_id || notification.batch_id), '_blank');
            }
          }
        });
      }
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
const formatTime = (dateString: string | undefined) => {
  if (!dateString) return 'Just now';

  try {
    const date = new Date(dateString);
    // Check if date is valid
    if (isNaN(date.getTime())) {
      return 'Just now';
    }
    return date.toLocaleString();
  } catch (error) {
    console.error('Error formatting date:', error);
    return 'Just now';
  }
};

// Function to download batch invoices
const downloadBatch = (notification: Notification) => {
  // Get the batch ID from the notification
  const batchId = notification.data?.batch_id || notification.batch_id;

  // Log the download attempt and notification data for debugging
  console.log('Downloading batch invoices:', batchId);
  console.log('Notification data:', notification);

  // Open the download URL in a new tab
  window.open(route('invoice.download-batch', batchId), '_blank');

  // Mark the notification as read
  markAsRead(notification);
};

// Get notification message for batch complete notifications
const getNotificationMessage = (notification: Notification) => {
  // Check if the notification has data property with message
  if (notification.data && notification.data.message) {
    return notification.data.message;
  }

  // Check if the notification has a message property
  if (notification.message) {
    return notification.message;
  }

  // Get total invoices from various possible locations
  const totalInvoices = notification.total_invoices ||
                       notification.data?.total_invoices ||
                       'all';

  // Get successful invoices from various possible locations
  const successfulInvoices = notification.successful_invoices ||
                            notification.data?.successful_invoices ||
                            totalInvoices;

  // Check if the notification indicates success
  const isSuccess = notification.success || notification.data?.success;

  // Return appropriate message based on success status
  if (isSuccess) {
    return `All ${totalInvoices !== 'all' ? totalInvoices : ''} invoices have been generated successfully.`;
  } else {
    return `${successfulInvoices} of ${totalInvoices} invoices were generated successfully.`;
  }
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
          // and update the notification if it already exists with the same ID
          const existingIndex = notifications.value.findIndex((n: Notification) => n.id === notification.id);

          if (existingIndex === -1) {
            // New notification - add it to the beginning of the list
            notifications.value = [notification, ...notifications.value];
          } else {
            // Update existing notification
            const updatedNotifications = [...notifications.value];
            updatedNotifications[existingIndex] = notification;
            notifications.value = updatedNotifications;
          }

          // Show toast notifications based on notification type
          if (notification.type === 'App\\Notifications\\InvoiceReady' && notification.data && notification.data.success) {
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
          } else if (notification.type === 'App\\Notifications\\InvoiceBatchProgress') {
            // Only show toast for 50% and 100% progress
            const significantProgress = [50, 100];
            if (notification.progress && significantProgress.includes(notification.progress)) {
              // For 100% progress, show a different message
              if (notification.progress === 100) {
                toast.success('Invoice Generation Complete', {
                  description: `All ${notification.total_invoices} invoices have been processed. Finalizing...`
                });
              } else {
                toast.info('Invoice Generation Progress', {
                  description: `${notification.processed_invoices} of ${notification.total_invoices} invoices processed (${notification.progress}%)`
                });
              }
            }
          } else if (notification.type === 'App\\Notifications\\InvoiceBatchComplete') {
            console.log('Received batch complete notification:', notification);

            // Show completion notification with download button
            if (notification.success) {
              toast.success('Invoices Ready for Download', {
                description: `All ${notification.total_invoices} invoices have been generated successfully.`,
                action: {
                  label: 'Download',
                  onClick: () => {
                    // Open the batch download route with the batch ID
                    window.open(route('invoice.download-batch', notification.data?.batch_id || notification.batch_id), '_blank');
                    // Mark as read after opening
                    markAsRead(notification);
                  }
                }
              });
            } else {
              toast.warning('Invoices Ready with Issues', {
                description: `${notification.successful_invoices || 0} of ${notification.total_invoices} invoices were generated successfully.`,
                action: {
                  label: 'Download',
                  onClick: () => {
                    // Open the batch download route with the batch ID
                    window.open(route('invoice.download-batch', notification.data?.batch_id || notification.batch_id), '_blank');
                    // Mark as read after opening
                    markAsRead(notification);
                  }
                }
              });
            }
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

  // Set up Echo listener for real-time updates
  setupEchoListener();

  // Set up polling to refresh notifications every 60 seconds (reduced from 10 seconds)
  // This is a fallback in case real-time notifications via Echo don't work
  const interval = setInterval(fetchNotifications, 60000);

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
        <div class="flex space-x-2">
          <Button variant="ghost" size="sm" class="text-xs" @click="() => fetchNotifications(true)">
            Refresh
          </Button>
          <Button v-if="hasUnread" variant="ghost" size="sm" class="text-xs" @click="markAllAsRead">
            Mark all as read
          </Button>
        </div>
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
              <!-- Handle different notification types -->
              <template v-if="notification.type === 'App\\Notifications\\InvoiceBatchProgress'">
                <p class="text-sm font-medium">Invoice Batch Progress</p>
                <p class="text-xs text-muted-foreground mt-1">{{ notification.message || `Processing ${notification.processed_invoices} of ${notification.total_invoices} invoices` }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
              </template>

              <template v-else-if="notification.type === 'App\\Notifications\\InvoiceBatchComplete'">
                <div class="flex justify-between items-start w-full">
                  <div>
                    <p class="text-sm font-medium">
                      {{ (notification.success || notification.data?.success) ? 'Invoices Ready for Download' : 'Invoices Ready with Issues' }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      {{ getNotificationMessage(notification) }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    class="ml-2 text-xs"
                    @click.stop="downloadBatch(notification)"
                  >
                    Download
                  </Button>
                </div>
              </template>

              <template v-else-if="notification.type === 'App\\Notifications\\InvoiceReady'">
                <p class="text-sm font-medium">
                  {{ notification.data && notification.data.success ? 'Invoice Ready' : 'Invoice Generation Failed' }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">{{ notification.data && notification.data.message }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.created_at) }}</p>
              </template>

              <!-- Default fallback for other notification types -->
              <template v-else>
                <p class="text-sm font-medium">Notification</p>
                <p class="text-xs text-muted-foreground mt-1">
                  {{ notification.data && notification.data.message ? notification.data.message : (notification.message || 'New notification') }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
              </template>
            </div>
          </div>
        </DropdownMenuItem>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
