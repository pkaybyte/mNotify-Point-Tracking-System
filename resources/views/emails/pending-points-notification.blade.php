<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pending Point Assignments - MNotify Pointing System</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #ffffff; padding: 20px; border-radius: 8px;">
        <h2 style="color: #333;">Hello {{ $supervisor->name }},</h2>

        <p>
            You currently have <strong>{{ $count }}</strong> point assignment(s) pending your review in the 
            <strong>MNotify Pointing System</strong>.
        </p>

        @if($pendingAssignments->count() > 0)
            <table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; margin-top: 15px; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th align="left">Assignment ID</th>
                        <th align="left">Reason</th>
                        <th align="left">Date Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->id }}</td>
                            <td>{{ $assignment->reason ?? 'No reason provided' }}</td>
                            <td>{{ $assignment->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p style="margin-top: 20px;">
            Please log in to the <a href="{{ url('/') }}" style="color: #007bff; text-decoration: none;">MNotify Pointing System</a> 
            to review and take the necessary action.
        </p>

        <p style="margin-top: 30px; color: #777; font-size: 13px;">
            — The MNotify Pointing System Team
        </p>
    </div>
</body>
</html>
