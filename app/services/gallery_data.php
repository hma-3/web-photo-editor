<?php
declare(strict_types=1);

function load_gallery_page_data(PDO $pdo, array $queryParams, int $viewerId): array
{
    $sortOptions = [
        'newest' => 'i.created_at DESC',
        'oldest' => 'i.created_at ASC',
        'likes_desc' => 'like_count DESC, i.created_at DESC',
        'likes_asc' => 'like_count ASC, i.created_at DESC',
        'comments_desc' => 'comment_count DESC, i.created_at DESC',
        'comments_asc' => 'comment_count ASC, i.created_at DESC',
    ];

    $sortKey = (string)($queryParams['sort'] ?? 'newest');
    if (!isset($sortOptions[$sortKey])) {
        $sortKey = 'newest';
    }
    $orderBySql = $sortOptions[$sortKey];

    $filterUserId = (int)($queryParams['user'] ?? 0);
    if ($filterUserId > 0) {
        $uidCheck = $pdo->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
        $uidCheck->execute([$filterUserId]);
        if (!$uidCheck->fetchColumn()) {
            $filterUserId = 0;
        }
    }
    $noUserFilter = $filterUserId > 0 ? 0 : 1;

    $memberCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $siteImageCount = (int)$pdo->query('SELECT COUNT(*) FROM images')->fetchColumn();

    if ($filterUserId > 0) {
        $authorsStmt = $pdo->prepare('
            SELECT DISTINCT u.id, u.username
            FROM users u
            WHERE EXISTS (SELECT 1 FROM images i WHERE i.user_id = u.id)
               OR u.id = :filter_author_id
            ORDER BY u.username ASC
        ');
        $authorsStmt->bindValue(':filter_author_id', $filterUserId, PDO::PARAM_INT);
        $authorsStmt->execute();
        $galleryAuthors = $authorsStmt->fetchAll();
    } else {
        $authorsStmt = $pdo->query('
            SELECT DISTINCT u.id, u.username
            FROM users u
            INNER JOIN images i ON i.user_id = u.id
            ORDER BY u.username ASC
        ');
        $galleryAuthors = $authorsStmt->fetchAll();
    }

    $authorNameById = [];
    foreach ($galleryAuthors as $authorRow) {
        $authorNameById[(int)$authorRow['id']] = (string)$authorRow['username'];
    }

    $popularStmt = $pdo->query('
        SELECT
            i.id,
            i.final_path,
            u.username,
            (
                SELECT COUNT(*)
                FROM likes l
                WHERE l.image_id = i.id
            ) AS like_count
        FROM images i
        INNER JOIN users u ON u.id = i.user_id
        ORDER BY like_count DESC, i.created_at DESC
        LIMIT 5
    ');
    $popularImages = $popularStmt->fetchAll();

    $perPage = 5;
    $galleryPage = max(1, (int)($queryParams['pg'] ?? 1));

    $countStmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM images i
        WHERE (:no_user_filter = 1 OR i.user_id = :filter_user_id)
    ');
    $countStmt->bindValue(':no_user_filter', $noUserFilter, PDO::PARAM_INT);
    $countStmt->bindValue(':filter_user_id', $filterUserId, PDO::PARAM_INT);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($galleryPage > $totalPages) {
        $galleryPage = $totalPages;
    }
    $offset = ($galleryPage - 1) * $perPage;

    $gallerySql = "
        SELECT
            i.id,
            i.final_path,
            i.created_at,
            u.username,
            COALESCE(lc.cnt, 0) AS like_count,
            COALESCE(cc.cnt, 0) AS comment_count,
            EXISTS(
                SELECT 1
                FROM likes l2
                WHERE l2.image_id = i.id
                  AND l2.user_id = :viewer_id
            ) AS liked_by_viewer
        FROM images i
        INNER JOIN users u ON u.id = i.user_id
        LEFT JOIN (
            SELECT image_id, COUNT(*) AS cnt
            FROM likes
            GROUP BY image_id
        ) lc ON lc.image_id = i.id
        LEFT JOIN (
            SELECT image_id, COUNT(*) AS cnt
            FROM comments
            GROUP BY image_id
        ) cc ON cc.image_id = i.id
        WHERE (:no_user_filter = 1 OR i.user_id = :filter_user_id)
        ORDER BY {$orderBySql}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($gallerySql);
    $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':no_user_filter', $noUserFilter, PDO::PARAM_INT);
    $stmt->bindValue(':filter_user_id', $filterUserId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();

    $commentsByImage = [];
    if ($images !== []) {
        $imageIds = array_map(static fn(array $row): int => (int)$row['id'], $images);
        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $cstmt = $pdo->prepare("
            SELECT c.image_id, c.content, c.created_at, u.username
            FROM comments c
            INNER JOIN users u ON u.id = c.user_id
            WHERE c.image_id IN ($placeholders)
            ORDER BY c.image_id ASC, c.created_at DESC
        ");
        $cstmt->execute($imageIds);
        while ($row = $cstmt->fetch()) {
            $iid = (int)$row['image_id'];
            $commentsByImage[$iid] ??= [];
            $commentsByImage[$iid][] = [
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'username' => $row['username'],
            ];
        }
    }

    return [
        'sortKey' => $sortKey,
        'filterUserId' => $filterUserId,
        'memberCount' => $memberCount,
        'siteImageCount' => $siteImageCount,
        'galleryAuthors' => $galleryAuthors,
        'authorNameById' => $authorNameById,
        'popularImages' => $popularImages,
        'galleryPage' => $galleryPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'images' => $images,
        'commentsByImage' => $commentsByImage,
    ];
}
