<?php

namespace Jobtech\LaravelChunky\Contracts;

use Jobtech\LaravelChunky\Http\Requests\AddChunkRequest;

interface MergeHandler
{
    /**
     * @param \Jobtech\LaravelChunky\Contracts\ChunkyManager $manager
     * @return \Jobtech\LaravelChunky\Handlers\MergeHandler
     */
    public function setManager(ChunkyManager $manager): MergeHandler;

    /**
     * @return \Jobtech\LaravelChunky\Contracts\ChunkyManager
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function manager(): ChunkyManager;

    /**
     * @param \Jobtech\LaravelChunky\Http\Requests\AddChunkRequest $request
     * @param string $folder
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch|string
     */
    public function dispatchMerge(AddChunkRequest $request, string $folder);

    /**
     * @param string $chunks_folder
     * @param string $merge_destination
     *
     * @return string
     */
    public function merge(string $chunks_folder, string $merge_destination): string;

    /**
     * @return \Jobtech\LaravelChunky\Contracts\MergeHandler
     */
    public static function instance(): MergeHandler;
}
