<?php
declare(strict_types=1);


//  @param array $file The uploaded file array from $_FILES['photo']
//   @param string $uploadDir Target directory for uploaded files
//  @return string The unique safe filename generated for storage
//  @throws Exception 
 
function uploadProduceImage(array $file, string $uploadDir = 'uploads/'): string {
    // 1. Verify standard file upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No produce image file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($uploadErrors[$errorCode] ?? 'Unknown upload error occurred.');
    }

    // 2. Validate maximum file size (Limit to 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxFileSize) {
        throw new Exception('Image file size exceeds the maximum permitted limit of 5MB.');
    }

    // 3. Validate file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        throw new Exception('Invalid file format. Allowed types: JPG, JPEG, PNG, WEBP, AVIF.');
    }

    // 4. Strict MIME-type checking using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif'
    ];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        throw new Exception('Invalid image mime type. Please upload a genuine image file.');
    }

    // 5. Ensure the destination directory exists and is writable
    $uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create destination upload directory.');
        }
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Destination upload folder is not writable by the web server.');
    }

    // 6. Generate a secure, collision-resistant unique filename
    $newFileName = 'produce_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
    $destination = $uploadDir . $newFileName;

    // 7. Move file from temp folder to permanent storage
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to move uploaded produce image to storage folder.');
    }

    return $newFileName;
}