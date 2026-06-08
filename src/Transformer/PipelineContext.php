<?php

declare(strict_types=1);

namespace Shikiphp\Transformer;

/**
 * Internal pairing of the ordered {@see TransformerPipeline} with its shared
 * {@see TransformerContext} for a single render.
 */
final class PipelineContext
{
    public function __construct(
        public readonly TransformerPipeline $pipeline,
        public readonly TransformerContext $context,
    ) {
    }
}
