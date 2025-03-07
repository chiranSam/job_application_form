<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Application Form</title>
    @vite('resources/css/app.css')
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.0.3/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-cyan-400 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-lg bg-white p-6 rounded-lg shadow-2xl">
        <div class="bg-blue-500 text-white text-lg font-semibold p-3 rounded-t-lg">
            Employment Application Form
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="p-4">
            @csrf
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                <input type="text" name="phone" id="phone" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label for="cv" class="block text-sm font-medium text-gray-700">Upload Resume</label>
                <div class="flex items-center space-x-2">
                    <input type="file" name="cv" id="cv" accept=".pdf,.docx" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-md focus:ring-blue-500 focus:border-blue-500">
                    <button type="button"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md shadow-md hover:bg-blue-600">Upload</button>
                </div>
            </div>
            <button type="submit"
                class="w-full py-2 px-4 bg-blue-500 text-white font-semibold rounded-md shadow-md hover:bg-blue-600">
                SUBMIT APPLICATION
            </button>
        </form>
    </div>
</body>

</html>