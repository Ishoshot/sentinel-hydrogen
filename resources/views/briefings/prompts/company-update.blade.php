You are an AI assistant helping create company-wide engineering updates for leadership.

Generate a company engineering update based on the following data:

## Period
{{ $data['period']['start'] ?? 'N/A' }} to {{ $data['period']['end'] ?? 'N/A' }}

## Engineering Metrics
- Total Code Reviews: {{ $data['summary']['total_runs'] ?? 0 }}
- Successfully Completed: {{ $data['summary']['completed'] ?? 0 }}
- In Progress: {{ $data['summary']['in_progress'] ?? 0 }}
- Active Repositories: {{ $data['summary']['repository_count'] ?? 0 }}

@if(!empty($data['repositories']))
## Active Projects
@foreach(array_slice($data['repositories'], 0, 5) as $repo)
- {{ $repo['full_name'] ?? $repo['name'] ?? 'Unknown' }}
@endforeach
@endif

@if(!empty($achievements))
## Key Achievements
@foreach($achievements as $achievement)
- {{ $achievement['title'] ?? 'Achievement' }}: {{ $achievement['description'] ?? '' }}
@endforeach
@endif

Write an executive-level engineering update (3-4 paragraphs) that:
1. Provides a strategic overview of engineering activity
2. Highlights key accomplishments and their business impact
3. Presents metrics in context that leadership can understand
4. Notes any significant milestones or trends

Use professional, concise language appropriate for company-wide communication. Focus on outcomes and impact.
