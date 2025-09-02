<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="ReadyMarket API",
 *     version="1.0.0",
 *     description="Complete REST API for Microsoft products marketplace. Includes products management, categories, users, MITEC payment processing, analytics, shopping cart and advanced authentication system.<br><br><strong>Developed by:</strong> Salvador Arturo Rodriguez Loera",
 *     @OA\Contact(
 *         name="salvador.rodriguez@readymind.ms",
 *         email="salvador.rodriguez@readymind.ms"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Development server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter JWT Bearer token in format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Categories",
 *     description="Microsoft product categories management"
 * )
 *
 * @OA\Tag(
 *     name="Products",
 *     description="Microsoft products management"
 * )
 *
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     required={"name", "identifier"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Microsoft 365"),
 *     @OA\Property(property="image", type="string", example="categories/m365.png"),
 *     @OA\Property(property="identifier", type="string", example="hp1C56hG"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_deleted", type="boolean", example=false),
 *     @OA\Property(property="sort_order", type="integer", example=2),
 *     @OA\Property(property="columns", type="integer", example=4),
 *     @OA\Property(property="description", type="string", example="Best of school, work and life"),
 *     @OA\Property(property="visits", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="active_products_count", type="integer", example=25)
 * )
 *
 * @OA\Schema(
 *     schema="BillingPeriod",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Monthly"),
 *     @OA\Property(property="code", type="string", example="P1M"),
 *     @OA\Property(property="description", type="string", example="Monthly billing"),
 *     @OA\Property(property="payment_count", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"category_id", "title", "product_id", "sku_id", "sku_title", "description"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Access LTSC 2021"),
 *     @OA\Property(property="product_id", type="string", example="DG7GMGF0D7FV"),
 *     @OA\Property(property="sku_id", type="string", example="0001"),
 *     @OA\Property(property="sku_title", type="string", example="Access LTSC 2021"),
 *     @OA\Property(property="publisher", type="string", example="Microsoft Corporation"),
 *     @OA\Property(property="description", type="string", example="Access LTSC 2021 database application"),
 *     @OA\Property(property="segment", type="string", example="Commercial"),
 *     @OA\Property(property="market", type="string", example="MX"),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="icon", type="string", example="products/DG7GMGF0D7FV/icon.png"),
 *     @OA\Property(property="slide_image", type="string", example="products/DG7GMGF0D7FV/slide.png"),
 *     @OA\Property(property="screenshot1", type="string", example="products/DG7GMGF0D7FV/image1.png"),
 *     @OA\Property(property="screenshot2", type="string", example="products/DG7GMGF0D7FV/image2.png"),
 *     @OA\Property(property="screenshot3", type="string", example="products/DG7GMGF0D7FV/image3.png"),
 *     @OA\Property(property="screenshot4", type="string", example="products/DG7GMGF0D7FV/image4.png"),
 *     @OA\Property(property="is_top", type="boolean", example=false),
 *     @OA\Property(property="is_bestseller", type="boolean", example=false),
 *     @OA\Property(property="is_slide", type="boolean", example=false),
 *     @OA\Property(property="is_novelty", type="boolean", example=false),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="category", ref="#/components/schemas/Category"),
 *     @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/ProductVariant"))
 * )
 *
 * @OA\Schema(
 *     schema="ProductVariant",
 *     type="object",
 *     required={"product_id", "billing_period_id", "term_duration", "billing_plan", "unit_price"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="billing_period_id", type="integer", example=1),
 *     @OA\Property(property="microsoft_id", type="string", example="CFQ7TTC0LFLS"),
 *     @OA\Property(property="term_duration", type="string", example="P1M"),
 *     @OA\Property(property="billing_plan", type="string", example="Monthly"),
 *     @OA\Property(property="unit_price", type="number", format="float", example=171.00),
 *     @OA\Property(property="erp_price", type="number", format="float", example=171.00),
 *     @OA\Property(property="pricing_tier_min", type="integer", example=1),
 *     @OA\Property(property="pricing_tier_max", type="integer", example=999),
 *     @OA\Property(property="effective_start_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="effective_end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="unit_of_measure", type="string", example="License"),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="catalog_item_id", type="string", example="DG7GMGF0D7FV:0001:123"),
 *     @OA\Property(property="is_purchasable", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="price_per_payment", type="number", format="float", example=171.00),
 *     @OA\Property(property="billing_period", ref="#/components/schemas/BillingPeriod")
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/api/v1/products?page=1"),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/api/v1/products?page=5"),
 *     @OA\Property(property="links", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/api/v1/products?page=2"),
 *     @OA\Property(property="path", type="string", example="http://localhost:8000/api/v1/products"),
 *     @OA\Property(property="per_page", type="integer", example=16),
 *     @OA\Property(property="prev_page_url", type="string", nullable=true),
 *     @OA\Property(property="to", type="integer", example=16),
 *     @OA\Property(property="total", type="integer", example=75)
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="errors", type="object",
 *         @OA\Property(property="field_name", type="array", @OA\Items(type="string", example="The field is required."))
 *     )
 * )
 */
class ApiController extends Controller
{
    //
}
