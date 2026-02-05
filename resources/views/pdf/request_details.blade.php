<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Request {{ $request->request_id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            position: relative;
        }

        h5 {
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .step-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 8px;
        }

        @page {
            margin: 40px 40px 80px 40px;
        }

        .watermark-wrapper {
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            z-index: -1000;
            transform: rotate(-30deg);
        }

        .watermark {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 150px;
            text-align: center;
            opacity: 0.20;
            color: #7933c9;
            font-size: 14px;
            font-weight: normal;
            pointer-events: none;
            user-select: none;
        }

        .watermark-container {
            position: fixed;
            top: 40px;
            left: 40px;
            right: 40px;
            bottom: 80px;
            z-index: -1000;
            pointer-events: none;
            overflow: hidden;
        }

        /* Individual watermark text */
        .watermark-text {
            position: absolute;
            color: #aa1c1c;
            opacity: 0.20;
            font-size: 10px;
            transform: rotate(-40deg);
            white-space: nowrap;
        }

        .checkmark {
            color: green;
            font-weight: bold;
        }

        .remarks {
            font-size: 11px;
            color: #333;
            padding-left: 20px;
        }

        .document-icon {
            width: 20px;
            height: 20px;
        }

        .step-title {
            font-weight: bold;
        }

        .heading {
            text-align: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo img {
            width: 120px;
            height: auto;
        }
    </style>
</head>

<body>

    {{-- Multiple watermarks --}}
    <div class="watermark-container">
        @for ($y = 0; $y < 12; $y++)
            @for ($x = 0; $x < 6; $x++)
                <div class="watermark-text"
                    style="
                    top: {{ $y * 150 }}px;
                    left: {{ $x * 200 }}px;
                 ">
                    {{ $request->userData?->name }}
                    | {{ $request->request_id }}
                    | {{ $request->created_at?->format('Y-m-d') }}
                </div>
            @endfor
        @endfor
    </div>

    <div class="logo">
        <img src="{{ public_path('assets/logo.png') }}" alt="Company Logo">
    </div>

    <h4 class="heading">INTERNAL MEMO</h4>
    <hr>

    <p><b>Request ID:</b> {{ $request->request_id }}</p>
    <p><b>Requested By:</b> {{ $request->userData?->name ?? '-' }}</p>
    <p><b>Date:</b> {{ $request->created_at?->format('Y-m-d') }}</p>
    <p><b>Request Type:</b> {{ $request->requestTypeData?->name ?? '-' }}</p>

    <hr>
    <h5>Request Details</h5>
    <p><b>Amount:</b> RM {{ number_format($request->amount, 2) }}</p>
    <p><b>Department:</b> {{ $request->departmentData?->name ?? '-' }}</p>
    <p><b>Priority:</b> {{ $request->priority ?? '-' }}</p>
    <p><b>Description:</b><br> {!! $request->description === strip_tags($request->description)
        ? nl2br(e($request->description))
        : $request->description !!}</p>
    {{-- <p><b>B    usiness Justification:</b><br>{!! nl2br(e($request->business_justification)) !!}</p> --}}

    <hr>
    <h5>Documents Submitted:</h5>
    <table>
        @forelse($request->documents as $doc)
            <tr>
                <td>📄</td>
                <td>{{ collect(explode('_', $doc->document))->slice(2)->implode('_') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">No documents uploaded.</td>
            </tr>
        @endforelse
    </table>

    <hr>
    <h5>Approval Process:</h5>

    <div class="step-title">1. Request Submission</div>
    <p>We seek requisition approval from the <b>Department Head</b> of
        <b>{{ $request->departmentData?->name ?? 'N/A' }}</b> for a
        <b>{{ $request->requestTypeData?->name ?? 'Request Type' }}</b> request type of an amount <b>RM
            {{ number_format($request->amount, 2) }}</b>, linked with Supplier
        <b>{{ $request->supplierData?->name ?? '-' }}</b>.
    </p>

    <table>
        <thead>
            <tr>
                <th>Prepared by</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $request->userData?->name }}</strong><br>
                    {{ $request->departmentData?->name ?? 'N/A' }}<br>
                    {{ $request->created_at?->format('d F Y') }}
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- @php
        $stepTitles = [
            2 => 'Department Review',
            3 => 'Blockchain Request Review',
        ];
    @endphp --}}

    @foreach ($workflowTimeline as $index => $step)
        <div class="step-title">{{ $index + 2 }}. {{ $step['step'] }}</div>
        <table>
            <thead>
                <tr>
                    <th>Approval Details</th>

                    <th style="text-align:center;">
                        @if (strtolower($step['status']) === 'approved')
                            ✔
                        @endif
                    </th>

                    <th>Approved</th>

                    <th style="text-align:center;">
                        @if (strtolower($step['status']) === 'rejected')
                            ✖
                        @endif
                    </th>

                    <th>Rejected</th>

                    <th>Other Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Reviewed by {{ $step['role'] ?? 'N/A' }}</strong><br>
                        {{ $step['assigned_user'] ?? '-' }}<br>
                        {{ $step['date'] ?? '-' }}
                    </td>
                    <td colspan="2"></td>
                    <td colspan="2"></td>
                    <td>
                        {{ $step['remark'] ?? '' }}
                    </td>
                </tr>
            </tbody>

        </table>
    @endforeach


    @if (!empty($lifecycleTimeline))
        <h5>Request Lifecycle</h5>
        <ul style="margin-left: 15px;">
            @foreach ($lifecycleTimeline as $item)
                <li>
                    {{ $item['label'] }}
                    —
                    {{ $item['date'] ?? '-' }}
                </li>
            @endforeach
        </ul>
    @endif



</body>

</html>
