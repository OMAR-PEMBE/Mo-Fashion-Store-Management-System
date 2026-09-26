<?php

namespace Tests\Feature;

use App\Jobs\SendMessage;
use App\Models\Category;
use App\Models\Message;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\BusinessSettingsService;
use App\Services\CustomerService;
use App\Services\MessageService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WhatsAppReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $this->actingAs($this->admin);
        $category = Category::create(['name' => 'Denim', 'slug' => 'denim']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => 'Jeans', 'product_code' => 'JEANS', 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $this->variant = $catalogue->saveVariant($product, ['sku' => 'JEANS', 'selling_price' => '45000', 'is_active' => 1, 'low_stock_threshold' => 2]);
        app(OpeningStockService::class)->confirm($this->variant, ['quantity' => 10, 'unit_cost' => '25000.00'], $this->admin);
        config(['messaging.whatsapp.driver' => 'log']);
    }

    private function sale(array $extra = [], string $key = 'sale:wa'): array
    {
        return $extra + ['request_key' => $key, 'payment_method' => 'CASH', 'payment_collected' => 1,
            'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '45000.00', 'discount_amount' => '0']]];
    }

    public function test_completing_a_sale_queues_one_receipt_that_the_log_driver_sends(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina Juma', 'whatsapp_number' => '0755 123 456'], $this->admin);
        $this->post('/sales', $this->sale(['customer_id' => $customer->id, 'send_receipt' => 1, 'receipt_whatsapp' => '0755 123 456']))
            ->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, 'WhatsApp receipt on its way to +255 755 123 456'));
        $sale = Sale::firstOrFail();
        $message = Message::firstOrFail();
        $this->assertSame(['whatsapp', 'receipt', '255755123456', 'sent', 'log', 1], [$message->channel, $message->purpose, $message->recipient, $message->status, $message->provider, $message->attempts]);
        $this->assertSame([$sale->id, $customer->id], [$message->sale_id, $message->customer_id]);
        $this->assertSame('Amina', $message->parameters[0]);
        $this->assertSame($sale->sale_number.' · TZS 45,000', $message->parameters[1]);
        $this->assertStringContainsString('/receipt/'.$sale->id.'?expires=', $message->parameters[2]);

        // A double-tapped "Complete sale" returns the same sale and never sends a second receipt.
        $this->post('/sales', $this->sale(['customer_id' => $customer->id, 'send_receipt' => 1, 'receipt_whatsapp' => '0755 123 456']))->assertRedirect();
        $this->assertSame(1, Message::count());

        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('Receipt on WhatsApp')->assertSee('+255 755 123 456')->assertSee('Sent')->assertSee('Send again');
        $this->get('/customers/'.$customer->id)->assertOk()->assertSee('Messages sent')->assertSee($sale->sale_number);
    }

    public function test_a_wrong_number_never_blocks_the_sale_and_can_be_fixed_and_resent(): void
    {
        $this->post('/sales', $this->sale(['send_receipt' => 1, 'receipt_whatsapp' => '12']))
            ->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, 'number looked wrong'));
        $sale = Sale::firstOrFail();
        $this->assertSame(0, Message::count());
        $this->post('/sales', $this->sale([], 'sale:no-receipt'))->assertRedirect();
        $this->assertSame(0, Message::count());

        $this->post('/sales/'.$sale->id.'/receipt/whatsapp', ['receipt_whatsapp' => 'abc'])->assertSessionHasErrors('receipt_whatsapp');
        $this->post('/sales/'.$sale->id.'/receipt/whatsapp', ['receipt_whatsapp' => '+255 713 000 111'])->assertRedirect('/sales/'.$sale->id);
        $this->post('/sales/'.$sale->id.'/receipt/whatsapp', ['receipt_whatsapp' => '0713 000 111'])->assertRedirect();
        $this->assertSame(['255713000111', '255713000111'], Message::pluck('recipient')->all());
        $this->assertSame(0, Message::whereNotNull('dedupe_key')->count());

        // Salespeople can only send receipts for their own sales.
        $other = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($other)->post('/sales/'.$sale->id.'/receipt/whatsapp', ['receipt_whatsapp' => '0713 000 111'])->assertForbidden();
    }

    public function test_the_receipt_link_opens_only_when_signed_and_unexpired_and_hides_staff_notes(): void
    {
        $this->post('/sales', $this->sale(['payment_method' => 'CASH']))->assertRedirect();
        $sale = Sale::firstOrFail();
        DB::table('sales')->where('id', $sale->id)->update(['notes' => 'Internal staff note']);
        $link = app(MessageService::class)->receiptLink($sale);
        auth()->logout();

        $this->get($link)->assertOk()->assertSee($sale->sale_number)->assertSee('TZS 45,000')->assertDontSee('Internal staff note')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/receipt/'.$sale->id)->assertForbidden();
        $this->get(str_replace('/receipt/'.$sale->id, '/receipt/'.($sale->id + 1), $link))->assertForbidden();
        $this->get(URL::temporarySignedRoute('receipts.public', now()->subMinute(), ['sale' => $sale->id]))->assertForbidden();
    }

    public function test_cloud_driver_sends_the_approved_template_and_records_refusals(): void
    {
        config(['messaging.whatsapp.driver' => 'cloud', 'messaging.whatsapp.cloud.phone_number_id' => '1234567890', 'messaging.whatsapp.cloud.access_token' => 'test-token']);
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.OK1']]], 200)
            ->push(['error' => ['message' => '(#131026) Message undeliverable']], 400)
            ->push(['error' => ['message' => 'Service temporarily unavailable']], 503)]);
        $this->post('/sales', $this->sale())->assertRedirect();
        $sale = Sale::firstOrFail();
        $service = app(MessageService::class);

        $sent = $service->queueReceipt($sale, '0755 123 456', $this->admin, resend: true)->fresh();
        $this->assertSame(['sent', 'wamid.OK1', 'whatsapp_cloud'], [$sent->status, $sent->provider_message_id, $sent->provider]);
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '255755123456' && $request['type'] === 'template'
            && $request['template']['name'] === 'sale_receipt'
            && count($request['template']['components'][0]['parameters']) === 3);

        $refused = $service->queueReceipt($sale, '0755 123 456', $this->admin, resend: true)->fresh();
        $this->assertSame('failed', $refused->status);
        $this->assertStringContainsString('Message undeliverable', $refused->error);

        // On the sync queue a temporary outage cannot be retried later, so it is recorded instead of breaking the page.
        $busy = $service->queueReceipt($sale, '0755 123 456', $this->admin, resend: true)->fresh();
        $this->assertSame('failed', $busy->status);
        $this->assertStringContainsString('busy (503)', $busy->error);
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('Not sent')->assertSee('Message undeliverable');

        config(['messaging.whatsapp.cloud.access_token' => null]);
        $unconfigured = $service->queueReceipt($sale, '0755 123 456', $this->admin, resend: true)->fresh();
        $this->assertStringContainsString('not connected yet', $unconfigured->error);
    }

    public function test_the_job_never_sends_a_message_twice(): void
    {
        $this->post('/sales', $this->sale())->assertRedirect();
        $message = app(MessageService::class)->queueReceipt(Sale::firstOrFail(), '0755 123 456', $this->admin, resend: true);
        $this->assertSame('sent', $message->fresh()->status);
        (new SendMessage($message->id))->handle(app(\App\Messaging\Messenger::class));
        $this->assertSame(1, $message->fresh()->attempts);
    }

    public function test_the_shop_setting_pre_ticks_the_receipt_option_at_the_counter(): void
    {
        $customer = app(CustomerService::class)->save(['full_name' => 'Amina Juma', 'phone' => '0755 123 456'], $this->admin);
        $review = fn () => $this->post('/pos/review', $this->sale(['customer_id' => $customer->id]))->assertOk();
        $review()->assertViewHas('autoReceipt', false)->assertSee('Send the receipt on WhatsApp')->assertSee('value="0755 123 456"', false);

        $settings = app(BusinessSettingsService::class);
        $values = $settings->values();
        $this->patch('/settings', array_replace($values, ['whatsapp_receipts' => '1', 'current_password' => 'password', 'revision' => $settings->revision($values)]))->assertSessionHasNoErrors();
        $review()->assertViewHas('autoReceipt', true);
        $this->get('/settings')->assertOk()->assertSee('Send receipts on WhatsApp');
    }
}
