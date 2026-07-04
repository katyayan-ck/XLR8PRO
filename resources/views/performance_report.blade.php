<!-- views/performance_report.blade.php -->
<h1>Performance Report</h1>
<p>Topic: {{ $topic }}</p>
<p>Combo: {{ json_encode($combo) }}</p>
<p>{{ ucfirst($metric) }}: {{ $data }}</p>
