# Event notifications and reminders

Event notices and reminders are sent synchronously using the configured Laravel mail transport. **Transport accepted** means the configured mail transport accepted the message; it does not confirm delivery to the recipient's inbox. Failed attempts can be retried from the event details page. Event notices go to pending and approved registrations; reminders go only to approved registrations. Rejected/cancelled registrations and attendance-only records are not recipients.

## Local scheduler

Run this alongside the application during local testing:

```sh
php artisan schedule:work
```

The scheduler checks due reminders every minute. With that frequency, the 24-hour reminder is eligible while an event is 23–24 hours away; the 1-hour reminder is eligible while it is 50–60 minutes away. These windows tolerate short scheduler delays. If the scheduler is stopped beyond the relevant window, that reminder is skipped. The application's mail settings must be configured in the runtime environment. The configured cache store must support atomic locks for overlap protection.

## Deployment

Configure the host's cron service to invoke Laravel's scheduler every minute from the application directory:

```cron
* * * * * cd /path/to/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

Apply the additive migration during deployment before enabling reminders:

```sh
php artisan migrate
```
