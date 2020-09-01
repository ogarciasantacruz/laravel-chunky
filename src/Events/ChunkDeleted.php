<?php

namespace Jobtech\LaravelChunky\Events;

use Illuminate\Queue\SerializesModels;
use Jobtech\LaravelChunky\Chunk;

class ChunkDeleted
{
    use SerializesModels;

    /**
     * @var \Jobtech\LaravelChunky\Chunk
     */
    private $chunk;

    public function __construct(Chunk $chunk)
    {
        $this->chunk = $chunk;
    }
}