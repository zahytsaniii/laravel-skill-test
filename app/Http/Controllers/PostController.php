<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::active()
            ->with('user')
            ->paginate(20);

        return response()->json($posts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 'posts.create';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_draft' => 'required|boolean',
            'published_at' => 'nullable|date',
        ]);

        // Jika publish tapi tidak ada waktu → publish sekarang
        if ($validated['is_draft'] === false && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = $request->user()->posts()->create($validated);

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::findOrFail($id);

        if (
            $post->is_draft ||
            ($post->published_at && $post->published_at->isFuture())
        ) {
            abort(404);
        }

        return response()->json($post->load('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::findOrFail($id);

        if ($request->user()->id !== $post->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_draft' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
        ]);

        if (
            array_key_exists('is_draft', $validated) &&
            $validated['is_draft'] === false &&
            empty($validated['published_at'])
        ) {
            $validated['published_at'] = now();
        }

        $post->update($validated);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = Post::findOrFail($id);

        if (auth()->id() !== $post->user_id) {
            abort(403);
        }

        $post->delete();

        return redirect()->route('posts.index');
    }
}
