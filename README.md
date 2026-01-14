# Laravel Async Audit Logger

**High-Performance, Zero-Latency Audit Trail for Enterprise Laravel Applications.**

This package provides an asynchronous audit logging system using **Redis** as a buffer and a **Daemon Worker** for bulk database insertion. It is designed to handle high-traffic applications where traditional synchronous logging would cause performance bottlenecks.

## 🚀 Why use this?

Traditional audit loggers insert a row into the database *synchronously* during the HTTP request.
* **Problem:** If you have 1,000 requests/sec, you are hitting the DB with 2,000 queries/sec (1 transaction + 1 log). This slows down response time and risks locking the table.
* **Solution:** This package pushes logs to Redis (memory) in <1ms. A background worker then processes them in batches (e.g., 100 logs at once). The user gets an instant response, and the database load is reduced by up to 90%.

## 📋 Requirements

* PHP ^8.1
* Laravel 10.x, 11.x, or 12.x
* **Redis Server** (Required for Production)
* PHP Extensions: `pcntl` (for worker signals), `redis`

## 📦 Installation

1.  **Install via Composer:**
    ```bash
    composer require adithwidhiantara/laravel-async-audit-logger
    ```

2.  **Publish Configuration & Migrations:**
    ```bash
    php artisan vendor:publish --provider="Adithwidhiantara\Audit\AuditingServiceProvider"
    ```

3.  **Run Migrations:**
    ```bash
    php artisan migrate
    ```

4.  **Environment Setup (.env):**
    Add these configurations to your `.env` file:

    ```dotenv
    # Driver: 'redis' (Production) or 'sync' (Local/Debugging)
    AUDIT_DRIVER=redis

    # Redis Connection (Recommend using a separate DB index, e.g., 'audit')
    AUDIT_REDIS_CONNECTION=default
    AUDIT_REDIS_KEY=audit_pkg:buffer

    # Worker Tuning
    AUDIT_BATCH_SIZE=100
    AUDIT_FLUSH_INTERVAL=5
    AUDIT_PRUNE_DAYS=90
    ```

## 🛠 Usage

### 1. Attach to Models
Simply use the `Auditable` trait on any Eloquent model you want to track.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Adithwidhiantara\Audit\Traits\Auditable; // <--- Import

class Product extends Model
{
    use Auditable; // <--- Use Trait

    protected $fillable = ['name', 'price', 'stock'];
}

```

That's it! Every `created`, `updated`, and `deleted` event will now be captured automatically.

### 2. Retrieving Logs

The trait provides a polymorphic relationship `audits`.

```php
$product = Product::find(1);

// Get all history for this product
foreach ($product->audits as $audit) {
    echo $audit->event; // 'updated'
    echo $audit->user->name; // 'John Doe'
    print_r($audit->old_values); // ['price' => 1000]
    print_r($audit->new_values); // ['price' => 1200]
}

```

## ⚙️ Operational Setup (Production)

Since this is an asynchronous system, you **must** run the background processes.

### 1. The Worker (Daemon)

This worker consumes logs from Redis and saves them to the Database. Use **Supervisor** to keep it running.

**Supervisor Config (`/etc/supervisor/conf.d/audit-worker.conf`):**

```ini
[program:audit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan audit:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/audit-worker.log
stopwaitsecs=60

```

*Tip: Increase `numprocs` if your traffic is extremely high.*

### 2. The Scheduler (Recovery & Pruning)

Add these commands to your `app/Console/Kernel.php` (or `routes/console.php` in Laravel 11+).

* **`audit:recover`**: Checks for failed logs (saved to JSON files during DB outages) and retries inserting them.
* **`audit:prune`**: Automatically deletes old logs (default: >90 days).

```php
// routes/console.php or Kernel.php

use Illuminate\Support\Facades\Schedule;

// Recover failed logs every hour
Schedule::command('audit:recover')->hourly()->runInBackground();

// Delete old logs daily
Schedule::command('audit:prune')->daily()->runInBackground();

```

## ⚖️ Pros & Cons

| Feature | Async (Redis) | Sync (Direct DB) |
| --- | --- | --- |
| **User Latency** | **Zero (<1ms)** | Standard (Waiting for DB) |
| **Throughput** | High (Bulk Insert) | Low (Row-by-row) |
| **Reliability** | **Fail-to-Disk** (Rescue JSON) | Fail = Error/Exception |
| **Complexity** | High (Needs Supervisor) | Low (Plug & Play) |
| **Real-time** | Delayed (max 5s) | Instant |

## 🛡 Disaster Recovery

If the Database goes down:

1. The Worker catches the exception.
2. Data is saved to `storage/logs/audit_rescue_*.json`.
3. The Worker continues processing new logs.
4. Once the DB is up, the scheduled `audit:recover` command restores the data from the JSON files.

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## 📄 License

[MIT](https://choosealicense.com/licenses/mit/)
