<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    // STORE
    public function test_guest_cannot_create_post()
    {
        $this->post('/posts', [])
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_create_draft_post()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/posts', [
                'title' => 'Draft Post',
                'content' => 'Content',
                'is_draft' => true,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('posts', [
            'title' => 'Draft Post',
            'is_draft' => true,
        ]);
    }

    public function test_store_validation_fails_with_invalid_data()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/posts', [])
            ->assertStatus(422);
    }

    // INDEX
    public function test_only_active_posts_are_listed()
    {
        Post::factory()->create(['is_draft' => true]);

        Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->addDay(), // scheduled
        ]);

        $active = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson('/posts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $active->id]);
    }

    public function test_index_response_contains_user_data()
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson('/posts')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'published_at',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
            ]);
    }

    // SHOW
    public function test_active_post_can_be_viewed()
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->getJson("/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $post->id]);
    }

    public function test_draft_post_returns_404()
    {
        $post = Post::factory()->create([
            'is_draft' => true,
        ]);

        $this->getJson("/posts/{$post->id}")
            ->assertStatus(404);
    }

    public function test_scheduled_post_returns_404()
    {
        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $this->getJson("/posts/{$post->id}")
            ->assertStatus(404);
    }

    // UPDATE
    public function test_author_can_update_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/posts/{$post->id}", [
                'title' => 'Updated Title',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_only_author_can_update_post()
    {
        $author = User::factory()->create();
        $other = User::factory()->create();

        $post = Post::factory()->for($author)->create();

        $this->actingAs($other)
            ->putJson("/posts/{$post->id}", ['title' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_update_validation_fails()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/posts/{$post->id}", ['title' => ''])
            ->assertStatus(422);
    }

    // DESTROY
    public function test_author_can_delete_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete("/posts/{$post->id}")
            ->assertRedirect('/posts');

        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_non_author_cannot_delete_post()
    {
        $author = User::factory()->create();
        $other = User::factory()->create();

        $post = Post::factory()->for($author)->create();

        $this->actingAs($other)
            ->delete("/posts/{$post->id}")
            ->assertStatus(403);
    }

    // CREATE & EDIT
    public function test_create_route_returns_string()
    {
        $this->get('/posts/create')
            ->assertSee('posts.create');
    }

    public function test_edit_route_returns_string()
    {
        $post = Post::factory()->create();

        $this->get("/posts/{$post->id}/edit")
            ->assertSee('posts.edit');
    }
}
