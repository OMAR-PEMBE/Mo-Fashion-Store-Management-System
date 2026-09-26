# Customer messaging

WhatsApp receipts are built (Phase A) and run in **test mode**: messages are written to
`storage/logs/laravel.log` instead of being sent. Going live is a configuration change,
not new code.

## How it works

```
Sale completed (or "Send again" on the sale page)
  → MessageService saves a row in the `messages` outbox (status: queued)
  → SendMessage job, run by the queue worker, hands it to the gateway for the channel
  → status becomes sent (or failed, with the reason); temporary errors retry 3 times
```

- **The sale never waits for WhatsApp.** A wrong number or an outage only affects the
  message; staff can fix the number and press **Send again** on the sale page.
- **No double receipts.** The automatic receipt for a sale is sent once, even if
  "Complete sale" is pressed twice. "Send again" always sends a new one.
- **Receipt link.** The message carries a private, signed link to the receipt
  (`/receipt/{sale}?expires=…&signature=…`). It needs no sign-in, expires after
  `RECEIPT_LINK_DAYS` (90), hides staff-only notes, and is not indexed by search engines.
  Changing any part of the link makes it stop working.
- **Where staff see it:** the review screen ("Send the receipt on WhatsApp", pre-filled
  from the customer), the sale page (status, error, Send again), and the customer page
  (Messages sent). Business settings has **Send receipts on WhatsApp**, which ticks the
  option by default when the customer has a number.

| Piece | File |
|---|---|
| Outbox table | `database/migrations/2026_09_27_000018_create_messages_table.php` |
| Settings | `config/messaging.php`, `.env` (`WHATSAPP_*`, `SMS_DRIVER`, `RECEIPT_LINK_DAYS`) |
| Queue + send | `app/Services/MessageService.php`, `app/Jobs/SendMessage.php` |
| Gateways | `app/Messaging/` (`LogGateway`, `WhatsAppCloudGateway`, `Messenger` picks one) |
| Customer receipt page | `app/Http/Controllers/ReceiptLinkController.php`, `resources/views/receipts/public.blade.php` |
| Tests | `tests/Feature/WhatsAppReceiptTest.php` |

## The queue worker

Messages are sent by the queue. `routes/console.php` runs
`queue:work --stop-when-empty` every minute from the **same cron entry as the backups**:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

So once the server has that cron line, nothing else is needed. On a local machine, send
waiting messages by hand with `php artisan queue:work --stop-when-empty`.

## Phase B: going live with WhatsApp

You need to do these steps; they cannot be done from the code.

1. **A new SIM number** for the shop's WhatsApp Business Platform. A number connected to
   the Cloud API generally cannot stay on the normal WhatsApp app, so don't move the
   shop's current WhatsApp number.
2. **Meta Business account** at business.facebook.com, with **business verification**
   completed (business registration documents).
3. In **developers.facebook.com**, create an app of type *Business*, add the **WhatsApp**
   product, add the new number, and note the **Phone number ID**.
4. Create a **System User** in Business settings, give it the WhatsApp permissions, and
   generate a **permanent access token**. (The 24-hour test token is not enough.)
5. In **WhatsApp Manager → Message templates**, create a template:
   - Name: `sale_receipt`, Category: **Utility**, Language: **English** (`en`)
   - Body:
     `Asante {{1}}! Your receipt {{2}} is ready: {{3}} Thank you for shopping with us.`
   - Sample values: `Amina`, `MFS-SAL-000123 · TZS 121,000`, `https://your-domain/receipt/123?...`
   - Wait for **Approved**. (Meta rejects bodies that start or end with a variable, which is
     why the text ends with a sentence.)
6. **Hosting with HTTPS** and `APP_URL` set to the real address, so the receipt link opens
   for customers.
7. Set in `.env`, then run `php artisan config:cache`:
   ```
   WHATSAPP_DRIVER=cloud
   WHATSAPP_PHONE_NUMBER_ID=<from step 3>
   WHATSAPP_ACCESS_TOKEN=<from step 4>
   WHATSAPP_RECEIPT_TEMPLATE=sale_receipt
   WHATSAPP_RECEIPT_LANGUAGE=en
   ```
8. Make one real sale to your own phone and check it arrives, then switch on **Send
   receipts on WhatsApp** in Business settings.

Costs: Meta charges per delivered utility message; check the current rate for Tanzania
in Meta's pricing page before switching on automatic receipts.

Still to add in Phase B: the **webhook** that receives Meta's delivered/read reports and
customer replies (needs the public HTTPS address). Until then, messages stop at "Sent".

## Later phases

- **SMS campaigns (Phase C):** add an SMS gateway class for the chosen provider (Beem
  Africa, Africa's Talking or similar) next to `LogGateway`; the outbox, queue, retries
  and history are shared. Marketing goes only to customers with *marketing_opt_in*, with
  STOP handling.
- **WhatsApp bot (Phase D):** needs the webhook above and product photos.
