<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Http\Request;
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
        ]);

        // Create a new Poll explicitly to avoid mass assignment issues
        $poll = new Poll();
        $poll->user_id = $request->user()->id;
        $poll->title = $request->title;
        $poll->question = $request->question;
        
        $poll->allow_multiple_choices = $request->isMultipleChoice ?? false;
        $poll->results_public = $request->isPublicResults ?? false;
        $poll->is_draft = $request->isDraft ?? false;

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
    public function show(string $token)
    {
        $poll = Poll::query()->with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return Response::json(['message' => 'Poll not found.'], 404);
        }

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
        ]);

        // Update the poll fields
        $poll->title = $request->title;
        $poll->question = $request->question;
        $poll->allow_multiple_choices = $request->isMultipleChoice ?? false;
        $poll->results_public = $request->isPublicResults ?? false;
        $poll->is_draft = $request->isDraft ?? false;
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

        // Step 2: Validate that an option_id was provided
        $request->validate([
            'option_id' => 'required|integer',
        ]);

        // Step 3: Verify the selected option actually belongs to this poll
        $option = PollOption::query()->where('id', $request->option_id)
            ->where('poll_id', $poll->id)
            ->first();

        if (!$option) {
            return Response::json(['message' => 'This option does not belong to this poll.'], 422);
        }

        // Step 4: Single-choice protection
        // If the poll does NOT allow multiple choices, check if the user already voted
        if (!$poll->allow_multiple_choices && $userId) {
            $existingVote = PollVote::query()->where('poll_id', $poll->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                return Response::json(['message' => 'You have already voted on this poll.'], 403);
            }
        }

        // Step 5: Save the new vote to the database
        $vote = new PollVote();
        $vote->poll_id = $poll->id;
        $vote->user_id = $userId;
        $vote->poll_option_id = $option->id;
        $vote->save();

        // Step 6: Return a success response
        return Response::json(['message' => 'Vote submitted successfully.'], 200);
    }
}
