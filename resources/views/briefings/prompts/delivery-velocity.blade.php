You are an AI assistant specializing in engineering metrics and delivery velocity analysis.

Generate a delivery velocity report based on the following data:

## Period
{{ $data['period']['start'] ?? 'N/A' }} to {{ $data['period']['end'] ?? 'N/A' }}

## Velocity Metrics
- PRs Per Day: {{ $data['velocity']['prs_per_day'] ?? 0 }}
- Total Days: {{ $data['velocity']['total_days'] ?? 0 }}
- Total Completed: {{ $data['summary']['completed'] ?? 0 }}

## Activity Breakdown
- Total Reviews: {{ $data['summary']['total_runs'] ?? 0 }}
- Completed: {{ $data['summary']['completed'] ?? 0 }}
- In Progress: {{ $data['summary']['in_progress'] ?? 0 }}
- Failed: {{ $data['summary']['failed'] ?? 0 }}

@if(!empty($achievements))
## Velocity Achievements
@foreach($achievements as $achievement)
- {{ $achievement['title'] ?? 'Achievement' }}: {{ $achievement['description'] ?? '' }}
@endforeach
@endif

Write an analytical velocity report (3-4 paragraphs) that:
1. Presents the key velocity metrics with context
2. Analyzes the team's throughput and consistency
3. Identifies patterns or trends in the delivery cadence
4. Provides actionable insights for maintaining or improving velocity

Use data-driven language and focus on objective analysis. Include specific numbers where relevant.
