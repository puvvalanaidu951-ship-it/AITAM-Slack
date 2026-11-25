<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        .admin-actions {
            width: 100%;
            
            display: flex;
            align-items: center;    
            justify-content:center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .admin-actions .btn {
            padding: 8px 16px;
            font-size: 14px;
        }
        </style>
</head>
<body>
      <!-- Administrative navigation -->
    <div class="admin-actions">

        <a class="btn btn-primary" href="moderation.php">
            Review content
        </a>

        <a class="btn btn-secondary" href="reports.php">
            Manage reports
        </a>

        <a class="btn btn-secondary" href="users.php">
            Manage members
        </a>

     

        <a class="btn btn-secondary" href="college_updates.php">
            Manage College Updates
        </a>

        <a class="btn btn-secondary" href="appeals.php">
            Content appeals
        </a>

        <a class="btn btn-secondary" href="ai_review.php">
            AI question insights
        </a>

        <a class="btn btn-secondary" href="answer_review.php">
            AI answer review
        </a>

        <a class="btn btn-secondary" href="audit_logs.php">
            Admin audit log
        </a>

    </div>
</body>
</html>