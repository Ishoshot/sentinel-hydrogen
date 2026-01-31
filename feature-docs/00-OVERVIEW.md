# Sentinel Feature Documentation

## Welcome to Sentinel

**Sentinel** is an AI-powered code review platform that helps engineering teams maintain code quality, security, and best practices at scale. Think of it as your team's most meticulous code reviewer who never gets tired, never misses a detail, and learns your team's conventions.

---

## What Makes Sentinel Different?

### The Problem We Solve

Imagine you're part of a growing engineering team. Pull requests are piling up. Senior developers are stretched thin reviewing code. Junior developers sometimes wait days for feedback. When reviews do happen, they're inconsistent—one reviewer catches security issues but misses performance problems; another focuses on style but overlooks logic bugs.

Sound familiar?

Sentinel solves this by providing:
- **Consistent, high-signal automated reviews** that never miss the important stuff
- **Intelligent findings** that explain *why* something is an issue and *how* to fix it
- **Team-specific learning** through custom guidelines and configuration
- **Analytics and insights** that help teams understand their code quality trends

### Our Philosophy

```
┌─────────────────────────────────────────────────────────────────┐
│                    SENTINEL'S GUIDING PRINCIPLES                │
├─────────────────────────────────────────────────────────────────┤
│  🎯  Signal over Noise    - Only surface what matters           │
│  🔍  Clarity over Clever  - Explain findings in plain language  │
│  🤝  Trust is Earned      - Build confidence through accuracy   │
│  ⚙️  Control over Opacity - You control what gets reviewed      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Features at a Glance

| Feature | What It Does | Who Benefits |
|---------|--------------|--------------|
| [**Automated Code Reviews**](./01-AUTOMATED-CODE-REVIEWS.md) | AI-powered analysis of every PR | Developers, Tech Leads |
| [**Briefings**](./02-BRIEFINGS.md) | AI-generated narrative reports | Engineering Managers, CTOs |
| [**@sentinel Commands**](./03-SENTINEL-COMMANDS.md) | Interactive AI assistance in PRs | Developers |
| [**Workspace Management**](./04-WORKSPACE-MANAGEMENT.md) | Multi-tenant team collaboration | Team Admins |
| [**GitHub Integration**](./05-GITHUB-INTEGRATION.md) | Seamless source control connection | DevOps, Platform Teams |
| [**Analytics Dashboard**](./06-ANALYTICS-DASHBOARD.md) | Code quality metrics and trends | Engineering Leadership |
| [**Configuration System**](./07-CONFIGURATION-SYSTEM.md) | Repository-level customization | Tech Leads, DevOps |
| [**Billing & Plans**](./08-BILLING-AND-PLANS.md) | Subscription and usage management | Team Admins |

---

## The Journey of a Code Review

Let's follow a pull request through Sentinel to understand how all the pieces fit together:

```
                                    ┌─────────────────────────────────────┐
                                    │         DEVELOPER OPENS PR           │
                                    └───────────────┬─────────────────────┘
                                                    │
                                                    ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                          📡 GITHUB WEBHOOK                                    │
│                                                                               │
│  GitHub sends a webhook to Sentinel when:                                    │
│  • PR is opened                                                               │
│  • New commits are pushed (synchronize)                                       │
│  • PR is reopened                                                             │
└───────────────────────────────────┬──────────────────────────────────────────┘
                                    │
                                    ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                          ⚙️ CONFIGURATION CHECK                              │
│                                                                               │
│  Sentinel checks:                                                             │
│  ✓ Is auto-review enabled for this repository?                               │
│  ✓ Does the PR match trigger rules (target branch, labels, etc.)?           │
│  ✓ Is there a valid API key configured (BYOK)?                              │
│  ✓ Is the sentinel config file valid?                                        │
└───────────────────────────────────┬──────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
              Skip Review                    Continue Review
                    │                               │
                    ▼                               ▼
        ┌─────────────────────┐     ┌─────────────────────────────────────────┐
        │  Record skip reason  │     │          🧠 CONTEXT BUILDING            │
        │  Post comment (if     │     │                                         │
        │  provider keys        │     │  Sentinel gathers:                      │
        │  missing)             │     │  • Diff and changed files               │
        └─────────────────────┘     │  • File contents for context            │
                                     │  • Semantic analysis (functions, etc.)  │
                                     │  • Team guidelines                       │
                                     │  • Review history                        │
                                     └───────────────┬─────────────────────────┘
                                                     │
                                                     ▼
                          ┌─────────────────────────────────────────────────────┐
                          │                    🤖 AI REVIEW                      │
                          │                                                      │
                          │  The AI (Claude/GPT-4) analyzes the code for:       │
                          │  • Security vulnerabilities                          │
                          │  • Logic errors and bugs                             │
                          │  • Performance issues                                │
                          │  • Maintainability concerns                          │
                          │  • Best practice violations                          │
                          └───────────────────────┬─────────────────────────────┘
                                                  │
                                                  ▼
                          ┌─────────────────────────────────────────────────────┐
                          │                 📝 FINDINGS GENERATED                │
                          │                                                      │
                          │  Each finding includes:                              │
                          │  • Severity (critical, high, medium, low, info)     │
                          │  • Category (security, performance, etc.)           │
                          │  • Location (file, line numbers)                     │
                          │  • Description and explanation                       │
                          │  • Suggested fix (when possible)                     │
                          └───────────────────────┬─────────────────────────────┘
                                                  │
                                                  ▼
                          ┌─────────────────────────────────────────────────────┐
                          │              💬 ANNOTATIONS POSTED                   │
                          │                                                      │
                          │  Findings are posted as:                             │
                          │  • Inline comments on specific lines                │
                          │  • Summary comment with overview                     │
                          │  • Review verdict (approve/request changes)         │
                          └─────────────────────────────────────────────────────┘
```

---

## Architecture Overview

Sentinel is built on a **multi-tenant, event-driven architecture** designed for enterprise-scale reliability:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              SENTINEL ARCHITECTURE                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   ┌──────────────────┐          ┌──────────────────┐         ┌──────────────┐  │
│   │    Frontend      │◄────────►│    API Layer     │◄───────►│   Database   │  │
│   │   (Dashboard)    │          │    (Laravel)     │         │  (PostgreSQL)│  │
│   └──────────────────┘          └────────┬─────────┘         └──────────────┘  │
│                                          │                                       │
│                                          ▼                                       │
│                           ┌──────────────────────────┐                          │
│                           │      Queue System        │                          │
│                           │   (Redis + Horizon)      │                          │
│                           └────────────┬─────────────┘                          │
│                                        │                                         │
│              ┌─────────────────────────┼─────────────────────────┐              │
│              │                         │                         │              │
│              ▼                         ▼                         ▼              │
│   ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐         │
│   │  Review Workers  │    │ Briefing Workers │    │ Webhook Workers  │         │
│   │  (AI Analysis)   │    │ (Report Gen)     │    │ (Event Process)  │         │
│   └────────┬─────────┘    └────────┬─────────┘    └──────────────────┘         │
│            │                       │                                             │
│            └───────────┬───────────┘                                             │
│                        ▼                                                         │
│              ┌──────────────────┐                                                │
│              │   AI Providers   │                                                │
│              │  (BYOK Model)    │                                                │
│              │                  │                                                │
│              │  ┌────────────┐  │                                                │
│              │  │ Anthropic  │  │                                                │
│              │  │ (Claude)   │  │                                                │
│              │  └────────────┘  │                                                │
│              │                  │                                                │
│              │  ┌────────────┐  │                                                │
│              │  │  OpenAI    │  │                                                │
│              │  │  (GPT-4)   │  │                                                │
│              │  └────────────┘  │                                                │
│              └──────────────────┘                                                │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Key Architectural Decisions

| Decision | Why We Made It |
|----------|----------------|
| **Action-based Architecture** | Business logic is centralized in Actions, making code testable and reusable |
| **BYOK (Bring Your Own Key)** | You control your AI costs; no surprise bills |
| **Multi-tenant by Default** | Complete data isolation between workspaces |
| **Event-driven Processing** | Decoupled components, easier scaling, better reliability |
| **Queue-first Operations** | Fast API responses, reliable background processing |

---

## Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| **Backend** | PHP 8.4 + Laravel 12 | Core application framework |
| **Database** | PostgreSQL 15+ | Primary data store with JSONB support |
| **Cache/Queue** | Redis | Session, cache, and job queue backend |
| **Queue Monitor** | Laravel Horizon | Queue dashboard and scaling |
| **AI Routing** | Prism PHP | Multi-provider AI abstraction |
| **Real-time** | Laravel Reverb | WebSocket for live updates |
| **Email** | Resend | Transactional email delivery |
| **Testing** | Pest 4 | Modern PHP testing framework |
| **Code Quality** | Laravel Pint + Larastan | Formatting and static analysis |

---

## Domain Vocabulary

Sentinel uses specific terminology consistently across the platform. Here are the key terms:

| Term | Definition |
|------|------------|
| **Workspace** | Your organization/team in Sentinel (the tenant boundary) |
| **Team** | The membership container within a Workspace |
| **Member** | A user who belongs to a Team |
| **Run** | A single review execution (analyzing a PR) |
| **Finding** | An issue or observation identified during a Run |
| **Annotation** | A Finding surfaced as a comment on the PR |
| **Connection** | The link between your Workspace and a Provider (e.g., GitHub) |
| **Installation** | Sentinel installed in your GitHub organization |
| **Repository** | A source code repo connected to Sentinel |
| **Provider Key** | Your BYOK API key for an AI provider |
| **Briefing** | An AI-generated narrative report |

---

## Getting Started

Ready to dive in? Here's the recommended reading order:

1. **[Automated Code Reviews](./01-AUTOMATED-CODE-REVIEWS.md)** - Understand the core review system
2. **[GitHub Integration](./05-GITHUB-INTEGRATION.md)** - How to connect your repositories
3. **[Configuration System](./07-CONFIGURATION-SYSTEM.md)** - Customize Sentinel for your team
4. **[Analytics Dashboard](./06-ANALYTICS-DASHBOARD.md)** - Track your code quality trends

For engineering managers and leadership:
- **[Briefings](./02-BRIEFINGS.md)** - AI-generated team reports
- **[Billing & Plans](./08-BILLING-AND-PLANS.md)** - Subscription management

For developers:
- **[@sentinel Commands](./03-SENTINEL-COMMANDS.md)** - Interactive AI assistance

---

## Contributing to This Documentation

Found something unclear or want to improve these docs? The documentation lives in the `/feature-docs` directory and follows these principles:

- **Engage, don't bore** - Use analogies, diagrams, and real examples
- **Progressive disclosure** - Start simple, add complexity as needed
- **Technical accuracy** - Every claim should match the codebase
- **Accessibility** - Write for both technical and non-technical readers

---

*Last updated: January 2026*
