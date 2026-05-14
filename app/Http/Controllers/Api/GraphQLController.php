<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GraphQLController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'operationName' => ['nullable', 'string'],
        ]);

        try {
            $result = GraphQL::executeQuery(
                $this->schema(),
                $validated['query'],
                null,
                null,
                $validated['variables'] ?? [],
                $validated['operationName'] ?? null,
            )->toArray();
        } catch (Throwable $exception) {
            return ApiResponse::error('GraphQL query failed', [$exception->getMessage()], 400);
        }

        if (! empty($result['errors'])) {
            return ApiResponse::error('GraphQL query failed', $result['errors'], 400);
        }

        return ApiResponse::success('GraphQL query executed successfully', $result['data'] ?? null);
    }

    private function schema(): Schema
    {
        $orderItemType = new ObjectType([
            'name' => 'OrderItem',
            'fields' => [
                'id' => Type::nonNull(Type::id()),
                'order_id' => Type::int(),
                'product_id' => Type::int(),
                'quantity' => Type::int(),
                'price' => Type::float(),
                'subtotal' => Type::float(),
                'created_at' => [
                    'type' => Type::string(),
                    'resolve' => fn ($item): ?string => $item->created_at?->toISOString(),
                ],
                'updated_at' => [
                    'type' => Type::string(),
                    'resolve' => fn ($item): ?string => $item->updated_at?->toISOString(),
                ],
            ],
        ]);

        $orderType = new ObjectType([
            'name' => 'Order',
            'fields' => [
                'id' => Type::nonNull(Type::id()),
                'checkout_id' => Type::int(),
                'user_id' => Type::int(),
                'invoice_number' => Type::string(),
                'total_amount' => Type::float(),
                'status' => Type::string(),
                'created_at' => [
                    'type' => Type::string(),
                    'resolve' => fn (Order $order): ?string => $order->created_at?->toISOString(),
                ],
                'updated_at' => [
                    'type' => Type::string(),
                    'resolve' => fn (Order $order): ?string => $order->updated_at?->toISOString(),
                ],
                'items' => [
                    'type' => Type::listOf($orderItemType),
                    'resolve' => fn (Order $order) => $order->items,
                ],
            ],
        ]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'order' => [
                    'type' => $orderType,
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                    ],
                    'resolve' => fn ($root, array $args): ?Order => Order::with('items')->find($args['id']),
                ],
            ],
        ]);

        return new Schema([
            'query' => $queryType,
        ]);
    }
}
