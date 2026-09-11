<?php

namespace App\Http\Controllers\Public;

use App\Models\Tool;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase 2 (E5-T6, §9 step 40) — the public "Tools" section (backlog item 13): downloadable
 * tools the admin publishes, each with per-download analytics (ToolDownloadController).
 */
class ToolController extends Controller
{
    public function index(): View
    {
        $tools = Tool::query()
            ->published()
            ->orderBy('sort_order')
            ->get();

        return view('public.tools.index', [
            'tools' => $tools,
        ]);
    }

    public function show(string $slug): View
    {
        $tool = Tool::where('slug', $slug)->published()->first();

        if (! $tool) {
            throw new NotFoundHttpException;
        }

        return view('public.tools.show', [
            'tool' => $tool,
        ]);
    }
}
