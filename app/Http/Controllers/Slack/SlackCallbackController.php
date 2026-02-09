<?php

declare(strict_types=1);

namespace App\Http\Controllers\Slack;

use App\Actions\Slack\HandleSlackCallback;
use App\Http\Requests\Slack\SlackCallbackRequest;
use Illuminate\Http\RedirectResponse;

final class SlackCallbackController
{
    /**
     * Handle the Slack OAuth callback.
     */
    public function __invoke(
        SlackCallbackRequest $request,
        HandleSlackCallback $handleCallback,
    ): RedirectResponse {
        if ($request->wasDeclined()) {
            return $this->redirectToError('Slack authorization was declined.');
        }

        $integration = $handleCallback->handle(
            code: $request->code(),
            state: $request->state(),
        );

        $workspace = $integration->workspace;

        if ($workspace === null) {
            return $this->redirectToError('Workspace not found.');
        }

        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return redirect()->to(
            $frontendUrl.'/'.$workspace->slug.'/settings/integrations?slack=connected'
        );
    }

    /**
     * Redirect to the workspaces page with an error message.
     */
    private function redirectToError(string $message): RedirectResponse
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return redirect()->to($frontendUrl.'/workspaces')
            ->with('error', $message);
    }
}
