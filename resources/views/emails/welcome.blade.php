<x-mail::message>
Hey {{ $userName }},

My name is Tobi — I'm the founder of Sentinel.

We built Sentinel because we wanted AI-powered code reviews that actually understand your codebase. Fast, context-aware, and developer-friendly.

Here are **3 things** to get you started:

<x-mail::panel>
**1. Connect your first repository**<br>
Link your GitHub repos to start getting automated reviews on every pull request.

**2. Configure your review preferences**<br>
Customize what Sentinel looks for — code style, security, performance, or all of the above.

**3. Check out the docs**<br>
Everything you need to know about getting the most out of Sentinel.
</x-mail::panel>

<x-mail::button :url="$dashboardUrl">
Go to Dashboard
</x-mail::button>

**P.S.:** What made you try Sentinel? I'd love to hear what brought you here.

Just hit reply — I read and respond to every email.

Cheers,<br>
**Tobi**<br>
<span style="color: #6b7280; font-size: 14px;">Founder, Sentinel</span>
</x-mail::message>
