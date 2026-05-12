<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Http\Request;

class ApiPollController extends Controller
{
    /**
     * Display a listing of the authenticated user's polls.
     */
    public function index(Request $request)
    {
        // Get all polls for the authenticated user, ordered by creation date
        $polls = $request->user()->polls()->orderBy('created_at', 'desc')->get();

        // Return the polls explicitly as a JSON response
        return response()->json($polls);
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
        return response()->json($poll, 201);
    }

    /**
     * Display the specified poll by its secret token.
     */
    public function show(string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        return response()->json($poll);
    }

    /**
     * Update the specified poll in storage.
     */
    public function update(Request $request, int $id)
    {
        // Find the poll by ID and ensure it belongs to the authenticated user
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
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

        // Delete all existing options for this poll to keep things simple
        PollOption::where('poll_id', $poll->id)->delete();

        // Recreate the new options from the request
        foreach ($request->options as $optionText) {
            $option = new PollOption();
            $option->poll_id = $poll->id;
            $option->label = $optionText;
            $option->save();
        }

        // Return the updated poll as a JSON response
        return response()->json($poll, 200);
    }

    /**
     * Remove the specified poll.
     */
    public function destroy(Request $request, int $id)
    {
        // Find the poll by ID, ensuring it belongs to the authenticated user
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        // Delete the poll from the database
        $poll->delete();

        return response()->json(['message' => 'Poll deleted successfully'], 200);
    }

    /**
     * Submit a vote on a poll.
     */
    public function vote(Request $request, string $token)
    {
        // Step 1: Find the poll by its secret token
        $poll = Poll::where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        // Step 2: Validate that an option_id was provided
        $request->validate([
            'option_id' => 'required|integer',
        ]);

        // Step 3: Verify the selected option actually belongs to this poll
        $option = PollOption::where('id', $request->option_id)
            ->where('poll_id', $poll->id)
            ->first();

        if (!$option) {
            return response()->json(['message' => 'This option does not belong to this poll.'], 422);
        }

        // Step 4: Single-choice protection
        // If the poll does NOT allow multiple choices, check if the user already voted
        if (!$poll->allow_multiple_choices) {
            $existingVote = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existingVote) {
                return response()->json(['message' => 'You have already voted on this poll.'], 403);
            }
        }

        // Step 5: Save the new vote to the database
        $vote = new PollVote();
        $vote->poll_id = $poll->id;
        $vote->user_id = $request->user()->id;
        $vote->poll_option_id = $option->id;
        $vote->save();

        // Step 6: Return a success response
        return response()->json(['message' => 'Vote submitted successfully.'], 200);
    }
}
