<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ChatbotRecommendationService
{
    public function recommend(string $message): array
    {
        if (!$this->hasActionableIntent($message)) {
            return $this->buildInstructionNeededResponse($message);
        }

        $provider = (string) config('services.openai.provider', 'local');
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($provider !== 'openai' || $apiKey === '') {
            return $this->buildOpenAiUnavailableResponse($message);
        }

        try {
            $inventory = $this->inventorySnapshot();
            $openAiRecommendation = $this->recommendWithOpenAI($message, $inventory);
            $openAiRecommendation = $this->enforceInventoryTruth($openAiRecommendation, $inventory);

            $openAiRecommendation['status'] = true;
            $openAiRecommendation['query'] = $message;
            $openAiRecommendation['notes'] = array_values(array_unique(array_merge(
                $openAiRecommendation['notes'] ?? [],
                ['La respuesta fue generada con OpenAI usando el inventario actual de Q-LESS.']
            )));

            return $this->normalizeResponseShape($openAiRecommendation);
        } catch (Throwable $exception) {
            $userMessage = $this->openAiFailureMessage($exception);

            Log::warning('Fallo la integracion con OpenAI para el chatbot.', [
                'message' => $message,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => false,
                'query' => $message,
                'project_title' => 'No pude consultar OpenAI',
                'summary' => $userMessage,
                'steps' => [],
                'available_products' => [],
                'unavailable_products' => [],
                'alternative_products' => [],
                'notes' => [
                    'No se uso la recomendacion local porque el chatbot esta configurado para responder con OpenAI.',
                ],
            ];
        }
    }

    private function openAiFailureMessage(Throwable $exception): string
    {
        $message = Str::lower($exception->getMessage());

        if (str_contains($message, 'incorrect api key') || str_contains($message, 'invalid api key') || str_contains($message, '401')) {
            return 'La API key de OpenAI no es valida o fue revocada. Crea una nueva key en OpenAI, ponla en OPENAI_API_KEY y reinicia/limpia la configuracion de Laravel.';
        }

        if (str_contains($message, 'model') && (str_contains($message, 'does not exist') || str_contains($message, 'not found'))) {
            return 'El modelo configurado en OPENAI_MODEL no esta disponible para esa API key. Cambia OPENAI_MODEL por un modelo disponible para tu cuenta.';
        }

        return 'El asistente no pudo generar una recomendacion en este momento. Revisa la API key, el modelo o la conexion del servidor.';
    }

    private function recommendWithOpenAI(string $message, array $inventory): array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout((int) config('services.openai.timeout', 25))
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-mini'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->buildSystemPrompt(),
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->buildUserPrompt($message, $inventory),
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'inventory_recommendation',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message')
                ?? $response->json('message')
                ?? 'OpenAI respondio con error HTTP ' . $response->status();

            throw new RuntimeException($errorMessage);
        }

        $payload = $response->json();
        $jsonText = $payload['output_text']
            ?? $payload['output'][0]['content'][0]['text']
            ?? null;

        if (!is_string($jsonText) || trim($jsonText) === '') {
            throw new RuntimeException('OpenAI no devolvio texto estructurado.');
        }

        $decoded = json_decode($jsonText, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('No fue posible interpretar la respuesta JSON de OpenAI.');
        }

        return $decoded;
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'Eres el asistente de Q-LESS, una papeleria virtual escolar.',
            'Ayudas con maquetas, dibujos, carteleras y trabajos escolares.',
            'Razona paso a paso internamente, pero no muestres tu razonamiento oculto.',
            'Debes basarte unicamente en el inventario proporcionado por el servidor.',
            'No inventes productos, precios, categorias, imagenes ni stock.',
            'Si un producto tiene stock mayor que 0, puede aparecer en available_products o alternative_products.',
            'Si un producto tiene stock 0, solo puede aparecer en unavailable_products.',
            'Si un material no existe en el inventario, menciona la limitacion en notes y usa alternativas reales del inventario.',
            'Sugiere alternativas unicamente a partir del inventario disponible con stock mayor que 0.',
            'Prioriza productos por utilidad real para el trabajo del usuario, no por orden del inventario.',
            'Responde solo con el JSON solicitado.',
            'Escribe siempre en espanol claro y amable.',
        ]);
    }

    private function buildUserPrompt(string $message, array $inventory): string
    {
        return "Consulta del usuario:\n{$message}\n\nInventario actual:\n"
            . json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'project_title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'steps' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'available_products' => $this->productListSchema(),
                'unavailable_products' => $this->productListSchema(),
                'alternative_products' => $this->productListSchema(),
                'notes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'project_title',
                'summary',
                'steps',
                'available_products',
                'unavailable_products',
                'alternative_products',
                'notes',
            ],
        ];
    }

    private function productListSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'nombre' => ['type' => 'string'],
                    'descripcion' => ['type' => 'string'],
                    'precio' => ['type' => 'number'],
                    'stock' => ['type' => 'integer'],
                    'categoria' => ['type' => 'string'],
                    'image_path' => ['type' => ['string', 'null']],
                    'reason' => ['type' => 'string'],
                ],
                'required' => [
                    'id',
                    'nombre',
                    'descripcion',
                    'precio',
                    'stock',
                    'categoria',
                    'image_path',
                    'reason',
                ],
            ],
        ];
    }

    private function inventorySnapshot(): array
    {
        return Producto::with('categoriaRelacion')
            ->orderByDesc('stock')
            ->orderBy('nombre')
            ->get()
            ->map(function (Producto $product) {
                return [
                    'id' => (int) $product->id,
                    'nombre' => (string) $product->nombre,
                    'descripcion' => (string) ($product->descripcion ?? ''),
                    'precio' => (float) $product->precio,
                    'stock' => (int) $product->stock,
                    'categoria' => (string) ($product->categoriaRelacion->nombre ?? $product->categoria ?? ''),
                    'image_path' => $product->image_path,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeResponseShape(array $response): array
    {
        foreach (['available_products', 'unavailable_products', 'alternative_products', 'steps', 'notes'] as $key) {
            if (!isset($response[$key]) || !is_array($response[$key])) {
                $response[$key] = [];
            }
        }

        foreach (['project_title', 'summary'] as $key) {
            if (!isset($response[$key]) || !is_string($response[$key]) || trim($response[$key]) === '') {
                $response[$key] = $key === 'project_title'
                    ? 'Recomendacion de materiales'
                    : 'Te dejo una recomendacion basada en el inventario actual de Q-LESS.';
            }
        }

        return $response;
    }

    private function enforceInventoryTruth(array $response, array $inventory): array
    {
        $inventoryById = collect($inventory)->keyBy('id');
        $usedIds = [];

        foreach (['available_products', 'unavailable_products', 'alternative_products'] as $listKey) {
            $items = collect($response[$listKey] ?? [])
                ->map(function ($item) use ($inventoryById, $listKey) {
                    if (!is_array($item) || !isset($item['id'])) {
                        return null;
                    }

                    $inventoryProduct = $inventoryById->get((int) $item['id']);

                    if (!$inventoryProduct) {
                        return null;
                    }

                    if ($listKey === 'unavailable_products' && (int) $inventoryProduct['stock'] > 0) {
                        return null;
                    }

                    if ($listKey !== 'unavailable_products' && (int) $inventoryProduct['stock'] <= 0) {
                        return null;
                    }

                    return [
                        ...$inventoryProduct,
                        'reason' => (string) ($item['reason'] ?? 'Recomendado segun tu solicitud.'),
                    ];
                })
                ->filter()
                ->filter(function ($item) use (&$usedIds) {
                    if (isset($usedIds[$item['id']])) {
                        return false;
                    }

                    $usedIds[$item['id']] = true;
                    return true;
                })
                ->values()
                ->all();

            $response[$listKey] = $items;
        }

        return $response;
    }

    private function buildOpenAiUnavailableResponse(string $message): array
    {
        return [
            'status' => false,
            'query' => $message,
            'project_title' => 'OpenAI no esta configurado',
            'summary' => 'Configura CHATBOT_PROVIDER=openai y OPENAI_API_KEY en el archivo .env para usar el asistente de OpenAI.',
            'steps' => [],
            'available_products' => [],
            'unavailable_products' => [],
            'alternative_products' => [],
            'notes' => [
                'No se uso la recomendacion local porque el chatbot debe responder con OpenAI.',
            ],
        ];
    }

    private function hasActionableIntent(string $message): bool
    {
        $normalizedMessage = $this->normalize($message);

        if ($normalizedMessage === '') {
            return false;
        }

        $words = preg_split('/\s+/', $normalizedMessage, -1, PREG_SPLIT_NO_EMPTY);
        $onlyGreetingWords = [
            'hola',
            'ola',
            'buenas',
            'buenos',
            'dias',
            'tardes',
            'noches',
            'hey',
            'holi',
            'saludos',
            'gracias',
            'ok',
            'vale',
        ];

        if ($words && count(array_diff($words, $onlyGreetingWords)) === 0) {
            return false;
        }

        $intentKeywords = [
            'hacer',
            'crear',
            'armar',
            'elaborar',
            'necesito',
            'quiero',
            'busco',
            'recomienda',
            'recomendar',
            'material',
            'materiales',
            'maqueta',
            'trabajo',
            'proyecto',
            'manualidad',
            'cartelera',
            'exposicion',
            'afiche',
            'poster',
            'dibujo',
            'dibujar',
            'ilustracion',
            'boceto',
            'colorear',
            'sistema solar',
            'planetas',
            'celula',
            'cartulina',
            'marcador',
            'marcadores',
            'lapiz',
            'lapices',
            'hoja',
            'hojas',
            'tijera',
            'tijeras',
            'regla',
            'pegante',
            'borrador',
            'tempera',
            'colores',
            'carton',
            'cuaderno',
            'libreta',
        ];

        foreach ($intentKeywords as $keyword) {
            if (str_contains($normalizedMessage, $this->normalize($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function buildInstructionNeededResponse(string $message): array
    {
        return [
            'status' => true,
            'query' => $message,
            'project_title' => 'Necesito una instruccion',
            'summary' => 'Dime que trabajo, maqueta, cartelera, dibujo o material necesitas para poder recomendarte productos del inventario.',
            'steps' => [
                'Escribe que quieres hacer, por ejemplo: "Necesito una maqueta del sistema solar".',
                'Si ya sabes los materiales, mencionalos para revisar disponibilidad.',
                'Con esa instruccion te mostrare productos disponibles y alternativas.',
            ],
            'available_products' => [],
            'unavailable_products' => [],
            'alternative_products' => [],
            'notes' => [
                'Tu mensaje no incluye una solicitud clara de trabajo o materiales.',
            ],
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
