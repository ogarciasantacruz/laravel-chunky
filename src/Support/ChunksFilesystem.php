<?php

namespace Jobtech\LaravelChunky\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jobtech\LaravelChunky\Chunk;
use Jobtech\LaravelChunky\Events\ChunkAdded;
use Jobtech\LaravelChunky\Events\ChunkDeleted;
use Jobtech\LaravelChunky\Exceptions\ChunkyException;
use Keven\Flysystem\Concatenate\Concatenate;
use Symfony\Component\HttpFoundation\File\File;
use Neutron\TemporaryFilesystem\Manager;

class ChunksFilesystem extends FileSystem
{
    /** @var Manager|null $manager */
    private static $manager;

    /** @var array */
    private static $collections = [];

    private function temporaryFilesystem() : Manager {
        if(static::$manager === null) {
            static::$manager = Manager::create();
        }

        return static::$manager;
    }

    private function addTemporaryContext(string $folder)
    {
        array_push(static::$collections, $folder);
    }

    /** {@inheritDoc} */
    public function disk($disk = null): ?string
    {
        if(!empty($disk) && is_string($disk)) {
            $this->disk = $disk;
        }

        return $this->disk;
    }

    /** {@inheritDoc} */
    public function folder($folder = null): ?string
    {
        if(!empty($folder) && is_string($folder)) {
            $this->folder = $folder;
        }

        return $this->folder;
    }

    /**
     * @param string $folder
     *
     * @return string
     */
    public function fullPath(string $folder): string
    {
        if (Str::startsWith($folder, $this->folder)) {
            return $folder;
        }

        return $this->folder.$folder;
    }

    /**
     * Retrieve every chunks' folder.
     *
     * @return array
     */
    public function folders(): array
    {
        return $this->filesystem()
            ->disk($this->disk)
            ->directories($this->folder);
    }

    /**
     * @param string|null $folder
     *
     * @return array
     */
    public function list($folder = null): array
    {
        $folder = $this->fullPath($folder);

        return $this->filesystem()
            ->disk($this->disk)
            ->files($folder);
    }

    /**
     * @param \Jobtech\LaravelChunky\Chunk $chunk
     * @param string $folder
     * @param array $options
     *
     * @return Chunk
     */
    public function store(Chunk $chunk, string $folder, $options = []): Chunk
    {
        if (! $chunk->getOriginalPath() instanceof File) {
            throw new ChunkyException('Path must be a file');
        }

        $path = $this->filesystem()->putFileAs(
            $this->fullPath($folder),
            $chunk->getPath(),
            $chunk->getSlug(),
            $options
        );
        $chunk->setPath($path);

        event(new ChunkAdded($chunk));

        return $chunk;
    }

    /**
     * Delete all chunks and, once empty, delete the folder.
     *
     * @param string $folder
     *
     * @return bool
     */
    public function delete(string $folder): bool
    {
        // Check temporary files
        if(in_array($folder, static::$collections)) {
            $this->temporaryFilesystem()->clean($folder);
        }

        $folder = $this->fullPath($folder);

        if (! $this->filesystem()->disk($this->disk)->exists($folder)) {
            return true;
        }

        foreach ($this->listChunks($folder) as $chunk) {
            $this->deleteChunk($chunk);
        }

        return $this->filesystem()->disk($this->disk)
            ->deleteDirectory($folder);
    }

    /**
     * Delete all chunks and, once empty, delete the folder.
     *
     * @param string $folder
     *
     * @return bool
     */
    public function deleteChunk(Chunk $chunk): bool
    {
        if (! $this->filesystem()->disk($this->disk)->exists($chunk->getPath())) {
            return true;
        }

        $deleted = $this->filesystem()
            ->disk($this->disk)
            ->delete($chunk->getPath());

        if($deleted) {
            event(new ChunkDeleted($chunk));
        }

        return $deleted;
    }

    /**
     * @param string $folder
     * @return \Illuminate\Support\Collection
     */
    public function listChunks(string $folder)
    {
        $folder = $this->fullPath($folder);
        $files = $this->list($folder);

        return collect($files)
            ->map(function ($path, $key) use ($folder, $files) {
                $filename = str_replace($folder.DIRECTORY_SEPARATOR, '', $path);
                $exploded_name = explode('_', $filename);
                $index = array_shift($exploded_name);
                $last = count($files) - 1 == $key;

                return new Chunk(intval($index), $path, $this->disk, $last);
            })->sortBy(function (Chunk $chunk) {
                return $chunk->getIndex();
            });
    }

    /**
     * @param string $path
     * @return bool
     */
    public function exists(string $path) : bool
    {
        return $this->filesystem()->disk($this->disk)->exists($path);
    }

    /**
     * @param string $folder
     * @return int
     */
    public function chunksCount(string $folder) : int
    {
        $folder = $this->fullPath($folder);

        return count($this->list($folder));
    }

    /**
     * @param string $path
     * @return int
     */
    public function chunkSize(string $path) : int
    {
        return $this->filesystem()->disk($this->disk)->size($path);
    }

    /**
     * @param $path
     * @return resource|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function readChunk($path)
    {
        return $this->filesystem()->disk($this->disk)->readStream($path);
    }

    /**
     * @param string $folder
     * @param \Illuminate\Support\Collection $chunks
     * @return \Illuminate\Support\Collection
     */
    public function createTemporaryFiles(string $folder, Collection $chunks): Collection
    {
        $this->addTemporaryContext($folder);

        return $chunks->map(function (Chunk $chunk) use($folder) {
            $resource = $this->filesystem()->disk($this->disk)->readStream($chunk->getPath());
            $location = $this->temporaryFilesystem()->createTemporaryFile($folder);

            $stream = fopen($location, 'w+b');

            if ( ! $stream || stream_copy_to_stream($resource, $stream) === false || ! fclose($stream)) {
                return false;
            }

            return $location;
        });
    }

    /**
     * Concatenate all chunks into final merge.
     *
     * @param string $chunk
     * @param array $chunks
     * @return bool
     */
    public function concatenate(string $chunk, array $chunks): bool {
        $this->filesystem()->disk($this->disk)->addPlugin(new Concatenate);

        return $this->filesystem()->disk($this->disk)->concatenate($chunk, ...$chunks);
    }
}