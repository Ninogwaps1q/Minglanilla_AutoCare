<x-layouts.app title="Notifications">
    <x-notification-center :notifications="$notifications" :unread-count="$unreadNotificationCount" :read-count="$readNotificationCount" :filter="$filter"
        index-route="customer.notifications.index" bulk-route="customer.notifications.bulk-update" read-route="customer.notifications.read" open-route="customer.notifications.open"
        description="Updates about your bookings, repairs, and payments." />
</x-layouts.app>
