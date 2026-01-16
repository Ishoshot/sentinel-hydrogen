<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $briefing->title ?? 'Briefing' }} - {{ $workspace->name ?? 'Sentinel' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #1a1a2e;
            background: #ffffff;
            padding: 40px;
        }

        .header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .header .meta {
            font-size: 14px;
            color: #6b7280;
        }

        .header .workspace {
            font-weight: 600;
            color: #3b82f6;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .narrative {
            font-size: 15px;
            line-height: 1.8;
            color: #374151;
            white-space: pre-wrap;
        }

        .achievements {
            display: grid;
            gap: 16px;
        }

        .achievement {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px 20px;
        }

        .achievement .title {
            font-size: 16px;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 4px;
        }

        .achievement .description {
            font-size: 14px;
            color: #0c4a6e;
        }

        .achievement .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 4px;
            background: #0ea5e9;
            color: white;
            margin-bottom: 8px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat .value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat .label {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .footer {
            margin-top: 48px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }

        .footer .logo {
            font-weight: 600;
            color: #3b82f6;
        }

        @media print {
            body {
                padding: 20px;
            }

            .achievement {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $briefing->title ?? 'Briefing Report' }}</h1>
        <div class="meta">
            <span class="workspace">{{ $workspace->name ?? 'Workspace' }}</span>
            &bull;
            Generated {{ $generation->created_at?->format('F j, Y \a\t g:i A') ?? now()->format('F j, Y \a\t g:i A') }}
            @if(isset($structuredData['period']))
                &bull;
                {{ $structuredData['period']['start'] ?? '' }} to {{ $structuredData['period']['end'] ?? '' }}
            @endif
        </div>
    </div>

    @if($narrative)
        <div class="section">
            <h2>Summary</h2>
            <div class="narrative">{{ $narrative }}</div>
        </div>
    @endif

    @if(isset($structuredData['summary']) && is_array($structuredData['summary']))
        <div class="section">
            <h2>Key Metrics</h2>
            <div class="stats">
                @if(isset($structuredData['summary']['total_runs']))
                    <div class="stat">
                        <div class="value">{{ number_format($structuredData['summary']['total_runs']) }}</div>
                        <div class="label">Total Runs</div>
                    </div>
                @endif
                @if(isset($structuredData['summary']['completed']))
                    <div class="stat">
                        <div class="value">{{ number_format($structuredData['summary']['completed']) }}</div>
                        <div class="label">Completed</div>
                    </div>
                @endif
                @if(isset($structuredData['summary']['prs_merged']))
                    <div class="stat">
                        <div class="value">{{ number_format($structuredData['summary']['prs_merged']) }}</div>
                        <div class="label">PRs Merged</div>
                    </div>
                @endif
                @if(isset($structuredData['summary']['repository_count']))
                    <div class="stat">
                        <div class="value">{{ number_format($structuredData['summary']['repository_count']) }}</div>
                        <div class="label">Repositories</div>
                    </div>
                @endif
                @if(isset($structuredData['velocity']['prs_per_day']))
                    <div class="stat">
                        <div class="value">{{ $structuredData['velocity']['prs_per_day'] }}</div>
                        <div class="label">PRs / Day</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if(!empty($achievements))
        <div class="section">
            <h2>Achievements</h2>
            <div class="achievements">
                @foreach($achievements as $achievement)
                    <div class="achievement">
                        @if(isset($achievement['type']))
                            <div class="badge">{{ ucfirst(str_replace('_', ' ', $achievement['type'])) }}</div>
                        @endif
                        <div class="title">{{ $achievement['title'] ?? 'Achievement' }}</div>
                        <div class="description">{{ $achievement['description'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        <p>Generated by <span class="logo">Sentinel</span> &mdash; AI-powered code review platform</p>
    </div>
</body>
</html>
