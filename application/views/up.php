<html>
	<head>
		<meta charset="utf-8" />
		<title>上传视频</title>
	</head>

	<body>
		<form action="/media_storage/index.php/media/upload" method="post" enctype="multipart/form-data">
			<input type="file" name="upfile" />
			<input type="submit" value="提交" />

		</form>
	</body>
</html>
