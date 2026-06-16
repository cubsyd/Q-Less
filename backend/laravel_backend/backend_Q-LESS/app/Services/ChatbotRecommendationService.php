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

        $apiKey = $this->openAiApiKey();
        $provider = $this->openAiProvider($apiKey);

        if ($provider !== 'openai' || $apiKey === '') {
            $localResponse = $this->recommendLocally($message);
            $localResponse['ai_provider'] = 'local';

            return $this->normalizeResponseShape($localResponse);
        }

        try {
            $inventory = $this->inventorySnapshot();
            $openAiRecommendation = $this->recommendWithOpenAI($message, $inventory);
            $openAiRecommendation = $this->enforceInventoryTruth($openAiRecommendation, $inventory);

            $openAiRecommendation['status'] = true;
            $openAiRecommendation['ai_provider'] = 'openai';
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

            $localResponse = $this->recommendLocally($message);
            $localResponse['ai_provider'] = 'local_fallback';
            $localResponse['notes'][] = $userMessage;
            $localResponse['notes'][] = 'Se uso recomendacion local porque OpenAI no respondio correctamente.';

            return $this->normalizeResponseShape($localResponse);
        }
    }

    private function openAiFailureMessage(Throwable $exception): string
    {
        $rawMessage = $this->sanitizeOpenAiError($exception->getMessage());
        $message = Str::lower($rawMessage);

        if (str_contains($message, 'incorrect api key') || str_contains($message, 'invalid api key') || str_contains($message, '401')) {
            return 'La API key de OpenAI no es valida o fue revocada. Crea una nueva key en OpenAI, ponla en OPENAI_API_KEY y reinicia/limpia la configuracion de Laravel.';
        }

        if (str_contains($message, 'model') && (str_contains($message, 'does not exist') || str_contains($message, 'not found'))) {
            return 'El modelo configurado en OPENAI_MODEL no esta disponible para esa API key. Detalle: ' . $rawMessage;
        }

        return 'OpenAI no pudo responder. Detalle: ' . $rawMessage;
    }

    private function recommendWithOpenAI(string $message, array $inventory): array
    {
        $models = array_values(array_unique(array_filter([
            $this->openAiModel(),
            'gpt-4o-mini',
        ])));

        $lastException = null;

        foreach ($models as $model) {
            try {
                return $this->requestOpenAiRecommendation($message, $inventory, $model);
            } catch (Throwable $exception) {
                $lastException = $exception;

                Log::warning('OpenAI no respondio con el modelo configurado.', [
                    'model' => $model,
                    'error' => $this->sanitizeOpenAiError($exception->getMessage()),
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('OpenAI no respondio correctamente.');
    }

    private function requestOpenAiRecommendation(string $message, array $inventory, string $model): array
    {
        $response = Http::withToken($this->openAiApiKey())
            ->timeout($this->openAiTimeout())
            ->acceptJson()
            ->asJson()
            ->withOptions([
                'proxy' => '',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'max_tokens' => 6000,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'inventory_recommendation',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildUserPrompt($message, $inventory),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message')
                ?? $response->json('message')
                ?? 'OpenAI respondio con error HTTP ' . $response->status();

            throw new RuntimeException('Modelo ' . $model . ': ' . $errorMessage);
        }

        $payload = $response->json();
        $jsonText = $payload['choices'][0]['message']['content'] ?? null;

        if (!is_string($jsonText) || trim($jsonText) === '') {
            throw new RuntimeException('OpenAI no devolvio texto estructurado.');
        }

        $decoded = $this->decodeJsonObject($jsonText);

        if (!is_array($decoded)) {
            throw new RuntimeException('No fue posible interpretar la respuesta JSON de OpenAI.');
        }

        return $decoded;
    }

    private function sanitizeOpenAiError(string $message): string
    {
        $sanitized = preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-***', $message) ?? $message;

        return Str::limit($sanitized, 320, '...');
    }

    private function openAiApiKey(): string
    {
        return trim($this->envString('OPENAI_API_KEY', (string) config('services.openai.api_key', '')));
    }

    private function openAiProvider(string $apiKey): string
    {
        if ($apiKey !== '') {
            return 'openai';
        }

        return trim($this->envString('CHATBOT_PROVIDER', (string) config('services.openai.provider', 'local'))) ?: 'local';
    }

    private function openAiModel(): string
    {
        return trim($this->envString('OPENAI_MODEL', (string) config('services.openai.model', 'gpt-4o-mini'))) ?: 'gpt-4o-mini';
    }

    private function openAiTimeout(): int
    {
        $timeout = (int) $this->envString('OPENAI_TIMEOUT', (string) config('services.openai.timeout', 60));

        return max(10, $timeout);
    }

    private function envString(string $key, string $fallback = ''): string
    {
        $value = getenv($key);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $serverValue = $_SERVER[$key] ?? null;

        if (is_string($serverValue) && trim($serverValue) !== '') {
            return $serverValue;
        }

        $envValue = $_ENV[$key] ?? null;

        if (is_string($envValue) && trim($envValue) !== '') {
            return $envValue;
        }

        return $fallback;
    }

    private function decodeJsonObject(string $jsonText): mixed
    {
        $cleanText = trim($jsonText);
        $cleanText = preg_replace('/^```(?:json)?\s*/i', '', $cleanText) ?? $cleanText;
        $cleanText = preg_replace('/\s*```$/', '', $cleanText) ?? $cleanText;

        $decoded = json_decode($cleanText, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($decoded)) {
            $decodedAgain = json_decode($decoded, true);

            if (is_array($decodedAgain)) {
                return $decodedAgain;
            }
        }

        $start = strpos($cleanText, '{');
        $end = strrpos($cleanText, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decodedObject = json_decode(substr($cleanText, $start, $end - $start + 1), true);

        if (is_string($decodedObject)) {
            return json_decode($decodedObject, true);
        }

        return $decodedObject;
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
            'Devuelve entre 6 y 10 pasos cuando el usuario pida como hacer una tarea; cada paso debe ser concreto, accionable y facil de seguir.',
            'Los pasos deben explicar preparacion, armado, decoracion, revision final y consejos practicos segun la tarea.',
            'Devuelve maximo 5 productos disponibles, 3 productos no disponibles, 4 alternativas y 5 notas.',
            'Prioriza productos por utilidad real para el trabajo del usuario, no por orden del inventario.',
            'Responde solo con el JSON solicitado.',
            'Escribe siempre en espanol claro, paciente y amable, como si guiaras a un estudiante paso a paso.',
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

    private function recommendLocally(string $message): array
    {
        $inventory = $this->inventorySnapshot();
        $normalizedMessage = $this->normalize($message);
        $scoredProducts = collect($inventory)
            ->map(function (array $product) use ($normalizedMessage) {
                $haystack = $this->normalize(implode(' ', [
                    $product['nombre'] ?? '',
                    $product['descripcion'] ?? '',
                    $product['categoria'] ?? '',
                ]));

                $score = 0;
                foreach (preg_split('/\s+/', $normalizedMessage, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    if (mb_strlen($word) < 3) {
                        continue;
                    }

                    if (str_contains($haystack, $word)) {
                        $score += 3;
                    }
                }

                foreach ($this->projectKeywordMap() as $projectKeyword => $productKeywords) {
                    if (!str_contains($normalizedMessage, $projectKeyword)) {
                        continue;
                    }

                    foreach ($productKeywords as $productKeyword) {
                        if (str_contains($haystack, $productKeyword)) {
                            $score += 5;
                        }
                    }
                }

                return [
                    ...$product,
                    'score' => $score,
                    'reason' => $score > 0
                        ? 'Coincide con lo que necesitas para este trabajo.'
                        : 'Puede servirte como material complementario.',
                ];
            })
            ->sortByDesc(fn (array $product) => ((int) $product['score'] * 100000) + (int) $product['stock'])
            ->values();

        $available = $scoredProducts
            ->filter(fn (array $product) => (int) $product['stock'] > 0 && (int) $product['score'] > 0)
            ->take(5)
            ->map(fn (array $product) => $this->stripScore($product))
            ->values()
            ->all();

        $alternatives = $scoredProducts
            ->filter(fn (array $product) => (int) $product['stock'] > 0 && !collect($available)->pluck('id')->contains($product['id']))
            ->take(4)
            ->map(fn (array $product) => $this->stripScore($product))
            ->values()
            ->all();

        $unavailable = $scoredProducts
            ->filter(fn (array $product) => (int) $product['stock'] <= 0 && (int) $product['score'] > 0)
            ->take(3)
            ->map(fn (array $product) => $this->stripScore([
                ...$product,
                'reason' => 'Este producto parece util para tu solicitud, pero no tiene stock disponible.',
            ]))
            ->values()
            ->all();

        if (count($available) === 0) {
            $available = $scoredProducts
                ->filter(fn (array $product) => (int) $product['stock'] > 0)
                ->take(5)
                ->map(fn (array $product) => $this->stripScore($product))
                ->values()
                ->all();
        }

        return [
            'status' => true,
            'query' => $message,
            'project_title' => 'Recomendacion de materiales',
            'summary' => count($available) > 0
                ? 'Claro, te ayudo. Estos productos del inventario actual pueden servirte y te dejo una guia sencilla para avanzar sin enredarte.'
                : 'No encontre coincidencias exactas, pero aun podemos resolverlo con alternativas disponibles y una guia paso a paso.',
            'steps' => $this->buildLocalSteps($message, $available, $alternatives),
            'available_products' => $available,
            'unavailable_products' => $unavailable,
            'alternative_products' => $alternatives,
            'notes' => [
                'Respuesta generada localmente con el inventario actual de Q-LESS.',
            ],
        ];
    }

    private function stripScore(array $product): array
    {
        unset($product['score']);

        return $product;
    }

    private function projectKeywordMap(): array
    {
        return [
            'maqueta' => ['cartulina', 'carton', 'pegante', 'tijera', 'regla', 'marcador', 'tempera'],
            'cartelera' => ['cartulina', 'marcador', 'colores', 'pegante', 'regla'],
            'afiche' => ['cartulina', 'marcador', 'colores', 'tempera'],
            'poster' => ['cartulina', 'marcador', 'colores', 'tempera'],
            'dibujo' => ['lapiz', 'colores', 'marcador', 'borrador', 'hoja'],
            'sistema solar' => ['cartulina', 'carton', 'tempera', 'colores', 'pegante'],
            'celula' => ['cartulina', 'plastilina', 'colores', 'marcador', 'pegante'],
            'manualidad' => ['cartulina', 'pegante', 'tijera', 'colores', 'tempera'],
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
                'Cuentame que tarea necesitas hacer, por ejemplo: "Necesito una maqueta del sistema solar".',
                'Si tu profesor te pidio una condicion especial, como tamano, colores o materiales obligatorios, incluyela en el mensaje.',
                'Si ya tienes algunos materiales en casa, tambien puedes decirme cuales para recomendarte solo lo que falta.',
                'Con esa informacion te dare un paso a paso claro, productos disponibles y alternativas si algo no esta en stock.',
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

    private function buildLocalSteps(string $message, array $available, array $alternatives): array
    {
        $normalizedMessage = $this->normalize($message);
        $materialNames = collect($available)
            ->merge($alternatives)
            ->pluck('nombre')
            ->filter()
            ->take(5)
            ->implode(', ');

        $materialsStep = $materialNames !== ''
            ? "Separa los materiales que vas a usar: {$materialNames}. Tenlos sobre la mesa antes de empezar para trabajar con calma."
            : 'Separa los materiales que tengas disponibles y revisa que esten limpios, completos y listos para usar.';

        if (str_contains($normalizedMessage, 'maqueta')) {
            return [
                $materialsStep,
                'Haz un boceto rapido de la maqueta en una hoja: marca la base, las piezas principales y donde ira cada rotulo.',
                'Prepara la base con carton o cartulina resistente; si necesitas recortar, mide primero con regla para que no quede torcido.',
                'Arma primero las piezas grandes y deja las pequenas para el final, asi puedes corregir la distribucion sin danar detalles.',
                'Pega cada parte con poca cantidad de pegante y espera unos minutos entre piezas para que la estructura quede firme.',
                'Agrega color, nombres y detalles visuales con marcadores, colores o temperas segun lo que tengas disponible.',
                'Revisa que la maqueta explique claramente el tema: si falta informacion, anade pequenos letreros o flechas.',
                'Antes de entregarla, mira la maqueta desde el frente y desde arriba para corregir manchas, piezas sueltas o espacios vacios.',
            ];
        }

        if (str_contains($normalizedMessage, 'cartelera') || str_contains($normalizedMessage, 'afiche') || str_contains($normalizedMessage, 'poster')) {
            return [
                $materialsStep,
                'Define el titulo principal y escribelo primero en borrador para calcular el espacio que ocupara.',
                'Divide la cartelera en secciones: titulo, informacion principal, imagenes y conclusion o mensaje final.',
                'Pasa el titulo a la cartulina con letras grandes y legibles; usa regla si quieres mantener una linea recta.',
                'Agrega dibujos, recortes o iconos que apoyen la informacion sin llenar demasiado la superficie.',
                'Escribe frases cortas y claras; evita parrafos largos para que se pueda leer rapido desde lejos.',
                'Resalta palabras importantes con color, subrayado o marcador, manteniendo una combinacion ordenada.',
                'Al final revisa ortografia, limpieza y equilibrio visual antes de pegar cualquier elemento definitivo.',
            ];
        }

        if (str_contains($normalizedMessage, 'dibujo') || str_contains($normalizedMessage, 'dibujar') || str_contains($normalizedMessage, 'ilustracion')) {
            return [
                $materialsStep,
                'Empieza con un boceto suave en lapiz, usando figuras simples para ubicar cada parte del dibujo.',
                'Corrige proporciones antes de remarcar; es mas facil ajustar lineas suaves que borrar trazos fuertes.',
                'Cuando la forma general este lista, marca los contornos principales con cuidado.',
                'Aplica color por capas suaves, empezando por tonos claros y dejando los oscuros para sombras o detalles.',
                'Agrega textura y detalles pequenos al final para que el dibujo no se vea plano.',
                'Limpia bordes, borra guias visibles y revisa que el resultado responda al tema pedido.',
            ];
        }

        return [
            $materialsStep,
            'Lee de nuevo la consigna de la tarea y anota que debe verse o explicarse en el resultado final.',
            'Haz un plan corto en una hoja: materiales, partes principales y orden de trabajo.',
            'Comienza por la estructura o contenido mas importante antes de decorar.',
            'Usa los productos disponibles para resolver lo esencial y deja las alternativas para detalles o reemplazos.',
            'Trabaja por partes y revisa cada avance antes de pegar, colorear o marcar de forma definitiva.',
            'Cuando termines, verifica limpieza, ortografia, firmeza y que la tarea se entienda sin tener que explicarla demasiado.',
        ];
    }
}
