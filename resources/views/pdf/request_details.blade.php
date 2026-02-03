<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request Details</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 5px;
            text-align: left;
        }
        h2, h3 {
            margin-top: 15px;
        }
    </style>
</head>
<body>

<h2>Request #{{ $request['request_id'] ?? 'N/A' }}</h2>

<p><strong>Description:</strong> {{ $request['description'] ?? 'N/A' }}</p>
<p><strong>Status:</strong> {{ $request['status'] ?? 'N/A' }}</p>
<p><strong>Requested By:</strong> {{ $request['requested_by']['name'] ?? 'N/A' }}</p>

<h3>Workflow History</h3>
<table>
    <thead>
        <tr>
            <th>Stage</th>
            <th>Role</th>
            <th>Assigned User</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @forelse($request['workflow_history'] ?? [] as $history)
            <tr>
                <td>{{ $history['stage'] ?? 'N/A' }}</td>
                <td>{{ $history['role'] ?? 'N/A' }}</td>
                <td>{{ $history['assigned_user'] ?? 'N/A' }}</td>
                <td>{{ $history['status'] ?? 'N/A' }}</td>
                <td>{{ $history['date'] ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No workflow history</td>
            </tr>
        @endforelse
    </tbody>
</table>

<h3>Documents</h3>
<table>
    <thead>
        <tr>
            <th>Document ID</th>
            <th>Document Name</th>
        </tr>
    </thead>
    <tbody>
        @forelse($request['documents'] ?? [] as $doc)
            <tr>
                <td>{{ $doc['document_id'] ?? 'N/A' }}</td>
                <td>{{ $doc['document'] ?? 'N/A' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">No documents</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
