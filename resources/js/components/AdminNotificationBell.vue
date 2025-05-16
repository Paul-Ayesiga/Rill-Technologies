<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'vue-sonner';

// Define notification type
interface Notification {
  id: string;
  type: string;
  data?: {
    success?: boolean;
    total_jobs?: number;
    success_jobs?: number;
    failed_jobs?: number;
    processed_jobs?: number;
    user_name?: string;
    user_id?: number;
    batch_id?: string;
    invoice_path?: string;
    progress?: number;
    message?: string;
    download_url?: string;
    total_invoices?: number;
  };
  read_at: string | null;
  created_at: string;
  time?: string;

  // These properties might be at the root level in some notifications
  success?: boolean;
  total_jobs?: number;
  success_jobs?: number;
  failed_jobs?: number;
  processed_jobs?: number;
  user_name?: string;
  user_id?: number;
  batch_id?: string;
  invoice_path?: string;
  total_invoices?: number;
  successful_invoices?: number;
  progress?: number;
  message?: string;
  download_url?: string;
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
    console.log('Skipping admin notification fetch - cooldown period active');
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

      // Always log in development, but we can't check process.env in the browser
      console.log('Fetched admin notifications:', data);

      // Log the types of notifications we received
      const notificationTypes = notifications.value.map((n: Notification) => n.type);
      console.log('Admin notification types:', notificationTypes);
    }
  } catch (error) {
    console.error('Error fetching admin notifications:', error);
  } finally {
    loading.value = false;
  }
};

// Mark a notification as read
const markAsRead = async (notification: Notification) => {
  try {
    const response = await fetch(route('notifications.mark-read', notification.id), {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Remove the notification from our list
    notifications.value = notifications.value.filter((n: Notification) => n.id !== notification.id);

    // Extract common data safely
    const userName = notification.data?.user_name || notification.user_name || 'user';
    const batchId = notification.data?.batch_id || notification.batch_id;
    const invoicePath = notification.data?.invoice_path || notification.invoice_path;
    const isSuccess = notification.data?.success || notification.success || false;

    // Handle different notification types
    if (notification.type === 'App\\Notifications\\Admin\\AdminInvoiceReady') {
      if (isSuccess && invoicePath) {
        // Show a toast notification
        toast.success('Invoice Ready', {
          description: `Invoice for ${userName} is ready for download.`,
          action: {
            label: 'Download',
            onClick: () => {
              window.open(route('admin.invoice.download', invoicePath), '_blank');
            }
          }
        });
      } else {
        toast.error('Invoice Generation Failed', {
          description: `Failed to generate invoice for ${userName}.`
        });
      }
    } else if (notification.type === 'App\\Notifications\\Admin\\AdminInvoiceBatchComplete') {
      console.log('Marking admin batch complete notification as read:', notification);

      // Get the total and successful invoices
      const totalInvoices = notification.data?.total_jobs || notification.total_jobs || notification.data?.total_invoices || notification.total_invoices || 0;
      const successfulInvoices = notification.data?.success_jobs || notification.success_jobs || notification.successful_invoices || 0;
      const failedJobs = notification.data?.failed_jobs || notification.failed_jobs || 0;

      // Show appropriate toast based on success/failure
      if (failedJobs === 0) {
        toast.success('Invoices Ready for Download', {
          description: `All ${totalInvoices} invoices for ${userName} have been generated successfully.`,
          action: {
            label: 'Download',
            onClick: () => {
              // Open the batch download route with the batch ID
              if (batchId) {
                window.open(route('admin.invoice.download-batch', batchId), '_blank');
              } else {
                toast.error('Error', { description: 'Could not find batch ID for download.' });
              }
            }
          }
        });
      } else {
        toast.warning('Invoices Ready with Issues', {
          description: `${successfulInvoices} of ${totalInvoices} invoices for ${userName} were generated successfully.`,
          action: {
            label: 'Download',
            onClick: () => {
              // Open the batch download route with the batch ID
              if (batchId) {
                window.open(route('admin.invoice.download-batch', batchId), '_blank');
              } else {
                toast.error('Error', { description: 'Could not find batch ID for download.' });
              }
            }
          }
        });
      }
    }
  } catch (error) {
    console.error('Error marking admin notification as read:', error);
    toast.error('Error', {
      description: 'Could not process the notification.'
    });
  }
};

// Mark all notifications as read
const markAllAsRead = async () => {
  try {
    const response = await fetch(route('notifications.mark-all-read'), {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Clear our notifications list
    notifications.value = [];

    toast.success('All notifications marked as read');
  } catch (error) {
    console.error('Error marking all notifications as read:', error);
    toast.error('Error', {
      description: 'Could not mark all notifications as read.'
    });
  }
};

// Download a batch of invoices
const downloadBatch = (notification: Notification) => {
  // Try to get the batch ID from different possible locations
  const batchId = notification.data?.batch_id || notification.batch_id;

  if (batchId) {
    window.open(route('admin.invoice.download-batch', batchId), '_blank');
    markAsRead(notification);
  } else {
    console.error('Missing batch ID in notification:', notification);
    toast.error('Error', {
      description: 'Could not find batch ID for download. Please try again later.'
    });
  }
};

// Format time for display
const formatTime = (timestamp: string) => {
  if (!timestamp) return '';

  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.round(diffMs / 60000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min${diffMins === 1 ? '' : 's'} ago`;

  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;

  return date.toLocaleDateString();
};

// Get notification message based on type and data
const getNotificationMessage = (notification: Notification) => {
  // Extract data safely from either notification.data or notification directly
  const totalInvoices = notification.data?.total_jobs || notification.total_jobs || notification.data?.total_invoices || notification.total_invoices || 'all';
  const successfulInvoices = notification.data?.success_jobs || notification.successful_invoices || 0;
  const failedJobs = notification.data?.failed_jobs || notification.failed_jobs || 0;
  const userName = notification.data?.user_name || 'user';

  // Return appropriate message based on failed jobs count
  if (failedJobs === 0) {
    return `All ${totalInvoices !== 'all' ? totalInvoices : ''} invoices for ${userName} have been generated successfully.`;
  } else {
    return `${successfulInvoices} of ${totalInvoices} invoices for ${userName} were generated successfully.`;
  }
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
          console.log('Received admin notification:', notification);

          // Add the notification to our list if it's not already there
          // and update the notification if it already exists with the same ID
          const existingIndex = notifications.value.findIndex((n: Notification) => n.id === notification.id);

          if (existingIndex !== -1) {
            // Update existing notification
            notifications.value[existingIndex] = notification;
          } else {
            // Add new notification
            notifications.value.push(notification);
          }

          // Show toast notifications for specific types
          if (notification.type === 'App\\Notifications\\Admin\\AdminInvoiceReady') {
            console.log('Received admin invoice ready notification:', notification);

            // Extract data safely
            const userName = notification.data?.user_name || notification.user_name || 'user';
            const invoicePath = notification.data?.invoice_path || notification.invoice_path;
            const isSuccess = notification.data?.success || notification.success || false;

            if (isSuccess && invoicePath) {
              toast.success('Invoice Ready', {
                description: `Invoice for ${userName} is ready for download.`,
                action: {
                  label: 'Download',
                  onClick: () => {
                    window.open(route('admin.invoice.download', invoicePath), '_blank');
                    // Mark as read after opening
                    markAsRead(notification);
                  }
                }
              });
            } else {
              toast.error('Invoice Generation Failed', {
                description: `Failed to generate invoice for ${userName}.`,
              });
            }
          } else if (notification.type === 'App\\Notifications\\Admin\\AdminInvoiceBatchProgress') {
            console.log('Received admin batch progress notification:', notification);

            // Only show toast for 50% and 100% progress to reduce notification spam
            const progress = notification.data?.progress || notification.progress || 0;
            const processedJobs = notification.data?.processed_jobs || notification.processed_jobs || 0;
            const totalJobs = notification.data?.total_jobs || notification.total_jobs || 0;
            const userName = notification.data?.user_name || notification.user_name || 'user';

            if (progress === 50 || progress === 100) {
              toast.info('Invoice Batch Progress', {
                description: `Generated ${processedJobs} of ${totalJobs} invoices for ${userName} (${progress}%).`,
              });
            }
          } else if (notification.type === 'App\\Notifications\\Admin\\AdminInvoiceBatchComplete') {
            console.log('Received admin batch complete notification:', notification);

            // Extract data safely from either notification.data or notification directly
            const failedJobs = notification.data?.failed_jobs || notification.failed_jobs || 0;
            const totalJobs = notification.data?.total_jobs || notification.total_jobs || 1;
            const successJobs = notification.data?.success_jobs || notification.success_jobs || 0;
            const userName = notification.data?.user_name || notification.user_name || 'user';
            const batchId = notification.data?.batch_id || notification.batch_id;

            // Show completion notification with download button
            if (failedJobs === 0) {
              toast.success('Invoices Ready for Download', {
                description: `All ${totalJobs} invoices for ${userName} have been generated successfully.`,
                action: {
                  label: 'Download',
                  onClick: () => {
                    // Open the batch download route with the batch ID
                    if (batchId) {
                      window.open(route('admin.invoice.download-batch', batchId), '_blank');
                      // Mark as read after opening
                      markAsRead(notification);
                    } else {
                      console.error('Missing batch ID in notification:', notification);
                      toast.error('Error', {
                        description: 'Could not find batch ID for download.'
                      });
                    }
                  }
                }
              });
            } else {
              toast.warning('Invoices Ready with Issues', {
                description: `${successJobs} of ${totalJobs} invoices for ${userName} were generated successfully.`,
                action: {
                  label: 'Download',
                  onClick: () => {
                    // Open the batch download route with the batch ID
                    if (batchId) {
                      window.open(route('admin.invoice.download-batch', batchId), '_blank');
                      // Mark as read after opening
                      markAsRead(notification);
                    } else {
                      console.error('Missing batch ID in notification:', notification);
                      toast.error('Error', {
                        description: 'Could not find batch ID for download.'
                      });
                    }
                  }
                }
              });
            }
          }
        });

      console.log(`Listening for admin notifications on App.Models.User.${userId}`);
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
        <h2 class="text-sm font-medium">Admin Notifications</h2>
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
              <template v-if="notification.type === 'App\\Notifications\\Admin\\AdminInvoiceBatchProgress'">
                <p class="text-sm font-medium">Invoice Batch Progress</p>
                <p class="text-xs text-muted-foreground mt-1">
                  {{ notification.data?.message || `Generated ${notification.data?.processed_jobs} of ${notification.data?.total_jobs} invoices for ${notification.data?.user_name}.` }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
              </template>

              <template v-else-if="notification.type === 'App\\Notifications\\Admin\\AdminInvoiceBatchComplete'">
                <div class="flex justify-between items-start w-full">
                  <div>
                    <p class="text-sm font-medium">
                      {{ (notification.data?.failed_jobs === 0) ? 'Invoices Ready for Download' : 'Invoices Ready with Issues' }}
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

              <template v-else-if="notification.type === 'App\\Notifications\\Admin\\AdminInvoiceReady'">
                <div class="flex justify-between items-start w-full">
                  <div>
                    <p class="text-sm font-medium">
                      {{ notification.data?.success ? 'Invoice Ready for Download' : 'Invoice Generation Failed' }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      {{ notification.data?.success ? `Invoice for ${notification.data?.user_name} is ready for download.` : `Failed to generate invoice for ${notification.data?.user_name}.` }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
                  </div>
                  <Button
                    v-if="notification.data?.success"
                    variant="outline"
                    size="sm"
                    class="ml-2 text-xs"
                    @click.stop="window.open(route('admin.invoice.download', notification.data.invoice_path), '_blank')"
                  >
                    Download
                  </Button>
                </div>
              </template>

              <!-- Default notification display -->
              <template v-else>
                <p class="text-sm font-medium">{{ notification.data?.message || 'New Notification' }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ formatTime(notification.time || notification.created_at) }}</p>
              </template>
            </div>
          </div>
        </DropdownMenuItem>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
