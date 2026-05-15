<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ApiPollController extends Controller
{
    /**
     * Display a listing of the authenticated user's polls.
     */
    public function index(Request $request)
    {
        // Get all polls for the authenticated user, ordered by creation date
        $polls = $request->user()->polls()->with(['options' => function ($query) { $query->withCount('votes'); }])->orderBy('created_at', 'desc')->get();

        // Return the polls explicitly as a JSON response
        return Response::json($polls);
    }

    /**
     * Store a newly created poll in storage.
     */
    public function store(Request $request)
    {
        // Simple validation for the basic fields and new options/settings
        $request->validate([
            'title' => 'nullable|string|max:255',
            'question' => 'required|string|max:255',
            'options' => 'required|array',
            'options.*' => 'required|string|max:255',
            'isMultipleChoice' => 'boolean',
            'isPublicResults' => 'boolean',
            'isDraft' => 'boolean',
            'duration' => 'nullable|integer|min:1',
        ]);

        // Create a new Poll explicitly to avoid mass assignment issues
        $poll = new Poll();
        $poll->user_id = $request->user()->id;
        $poll->title = $request->title;
        $poll->question = $request->question;
        
        $poll->allow_multiple_choices = $request->isMultipleChoice ?? false;
        $poll->results_public = $request->isPublicResults ?? false;
        $poll->is_draft = $request->isDraft ?? false;
        $poll->duration = $request->duration;

        if (!$poll->is_draft) {
            $poll->started_at = $poll->started_at ?? now();
            if ($poll->duration) {
                $poll->ends_at = $poll->started_at->copy()->addHours($poll->duration);
            } else {
                $poll->ends_at = null;
            }
        } else {
            $poll->started_at = null;
            $poll->ends_at = null;
        }

        // Generate a random 10-character string for the secret token
        $poll->secret_token = \Illuminate\Support\Str::random(10); 
        
        // Save the new poll to the database
        $poll->save();

        // Create the associated options
        foreach ($request->options as $optionText) {
            $option = new PollOption();
            $option->poll_id = $poll->id;
            $option->label = $optionText;
            $option->save();
        }

        // Return the newly created poll as a JSON response with status 201 Created
        return Response::json($poll, 201);
    }

    /**
     * Display the specified poll by its secret token.
     */
    public function show(Request $request, string $token)
    {
        // Identify the current user (may be null for anonymous visitors)
        $currentUser = auth('sanctum')->user();

        // Load the poll without vote counts first
        $poll = Poll::query()->where('secret_token', $token)->first();

        if (!$poll) {
            return Response::json(['message' => 'Poll not found.'], 404);
        }

        // Determine if the current user is the owner of this poll
        $isOwner = $currentUser && $currentUser->id === $poll->user_id;

        // Security: block access to a draft for anyone who is not the owner
        if ($poll->is_draft && !$isOwner) {
            return Response::json(['message' => 'Ce sondage n\'est pas encore disponible.'], 403);
        }

        // Only load vote counts if results are public OR if the user is the owner
        // This prevents leaking private statistics through the JSON response
        if ($poll->results_public || $isOwner) {
            $poll->load(['options' => function ($query) {
                $query->withCount('votes');
            }]);
        } else {
            // Load options without vote counts for private polls
            $poll->load('options');
        }

        // Add is_expired attribute dynamically
        $poll->is_expired = $poll->ends_at ? now()->greaterThan($poll->ends_at) : false;

        return Response::json($poll);
    }

    /**
     * Update the specified poll in storage.
     */
    public function update(Request $request, int $id)
    {
        // Find the poll by ID and ensure it belongs to the authenticated user
        $poll = Poll::query()->where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return Response::json(['message' => 'Poll not found.'], 404);
        }

        // Apply the same validation as the store method
        $request->validate([
            'title' => 'nullable|string|max:255',
            'question' => 'required|string|max:255',
            'options' => 'required|array',
            'options.*' => 'required|string|max:255',
            'isMultipleChoice' => 'boolean',
            'isPublicResults' => 'boolean',
            'isDraft' => 'boolean',
            'duration' => 'nullable|integer|min:1',
        ]);

        // Update the poll fields
        $poll->title = $request->title;
        $poll->question = $request->question;
        $poll->allow_multiple_choices = $request->isMultipleChoice ?? false;
        $poll->results_public = $request->isPublicResults ?? false;
        $poll->is_draft = $request->isDraft ?? false;
        $poll->duration = $request->duration;

        if (!$poll->is_draft) {
            $poll->started_at = $poll->started_at ?? now();
            if ($poll->duration) {
                $poll->ends_at = $poll->started_at->copy()->addHours($poll->duration);
            } else {
                $poll->ends_at = null;
            }
        } else {
            $poll->started_at = null;
            $poll->ends_at = null;
        }
        $poll->save();

        // Update existing options intelligently to preserve votes
        $existingOptions = PollOption::query()->where('poll_id', $poll->id)->orderBy('id', 'asc')->get();
        foreach ($request->options as $index => $optionText) {
            if (isset($existingOptions[$index])) {
                $opt = $existingOptions[$index];
                $opt->label = $optionText;
                $opt->save();
            } else {
                $opt = new PollOption();
                $opt->poll_id = $poll->id;
                $opt->label = $optionText;
                $opt->save();
            }
        }
        // Delete any trailing options that were removed in the new request
        if ($existingOptions->count() > count($request->options)) {
            for ($i = count($request->options); $i < $existingOptions->count(); $i++) {
                PollOption::destroy($existingOptions[$i]->id);
            }
        }

        // Return the updated poll as a JSON response
        return Response::json($poll, 200);
    }

    /**
     * Remove the specified poll.
     */
    public function destroy(Request $request, int $id)
    {
        // Find the poll by ID, ensuring it belongs to the authenticated user
        $poll = Poll::query()->where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return Response::json(['message' => 'Poll not found.'], 404);
        }

        // Delete the poll from the database
        Poll::destroy($poll->id);

        return Response::json(['message' => 'Poll deleted successfully'], 200);
    }

    /**
     * Submit a vote on a poll.
     */
    public function vote(Request $request, string $token)
    {
        $userId = $request->user() ? $request->user()->id : null;

        // Step 1: Find the poll by its secret token
        $poll = Poll::query()->where('secret_token', $token)->first();

        if (!$poll) {
            return Response::json(['message' => 'Poll not found.'], 404);
        }

        // Block votes on draft polls
        if ($poll->is_draft) {
            return Response::json(['message' => 'Impossible de voter sur un sondage en brouillon.'], 403);
        }

        // Check if poll is expired
        $isExpired = $poll->ends_at ? now()->greaterThan($poll->ends_at) : false;
        if ($isExpired) {
            return Response::json(['message' => 'Ce sondage est expiré, vous ne pouvez plus voter.'], 403);
        }

        // Step 2: Validate that option_ids is a non-empty array of integers
        $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'required|integer',
        ]);

        // Step 3: Reject single-choice polls when more than one option is submitted
        if (!$poll->allow_multiple_choices && count($request->option_ids) > 1) {
            return Response::json(['message' => 'Ce sondage n\'autorise qu\'un seul choix.'], 422);
        }

        // Step 4: Verify ALL submitted options belong to this poll
        $optionIds = (array) $request->option_ids;
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = PollOption::query();
        $options = $query->whereIn('id', $optionIds)
            ->where('poll_id', $poll->id)
            ->get();

        if ($options->count() !== count($optionIds)) {
            return Response::json(['message' => 'Une ou plusieurs options sont invalides.'], 422);
        }

        // Step 5: Uniqueness check — block if the connected user has already voted on this poll
        if ($userId) {
            $existingVote = PollVote::query()
                ->where('poll_id', $poll->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                return Response::json(['message' => 'Vous avez déjà voté pour ce sondage.'], 403);
            }
        }

        // Step 6: Save all votes in a single database transaction
        DB::transaction(function () use ($poll, $options, $userId) {
            foreach ($options as $option) {
                $vote = new PollVote();
                $vote->poll_id        = $poll->id;
                $vote->user_id        = $userId;
                $vote->poll_option_id = $option->id;
                $vote->save();
            }
        });

        // Step 7: Return a success response
        return Response::json(['message' => 'Vote enregistré avec succès.'], 200);
    }
}
