<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\FollowUpEmail;
use Carbon\Carbon;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Aws\Textract\TextractClient;
use Exception;


class ApplicationController extends Controller
{
    public function submit(Request $request)
    {
        // Validate input fields
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|min:10|max:15',
            'cv' => 'required|mimes:pdf|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Ensure file exists
        if (!$request->hasFile('cv')) {
            \Log::error("No file was uploaded.");
            return redirect()->back()->withErrors(['error' => 'No file uploaded.'])->withInput();
        }

        // Upload CV to AWS S3
        $path = $request->file('cv')->store('JobApplications', 's3');
        if (!$path) {
            \Log::error("File upload failed.");
            return redirect()->back()->withErrors(['error' => 'File upload failed.'])->withInput();
        }

        // Generate S3 File URL
        $cvUrl = Storage::disk('s3')->url($path);
        \Log::info("File successfully uploaded: " . $cvUrl);

        try {
            // ✅ Extract text from CV using AWS Textract (OCR)
            $text = $this->extractTextFromS3($path);

            if (empty($text)) {
                \Log::warning("No text extracted from CV.");
            }

            // ✅ Parse extracted text dynamically
            $parsedData = $this->parseCVText($text);

            // ✅ Store data in Google Sheets
            $this->saveToGoogleSheets($request, $parsedData, $cvUrl);

            $this->sendWebhook($parsedData, $cvUrl, 'testing');

            $this->scheduleFollowUpEmail($request->email, $request->name);

            return redirect()->back()->with('success', 'Your application has been submitted successfully!');

        } catch (Exception $e) {
            \Log::error("Error in CV Submission: " . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to process your application. Please try again later.'])->withInput();
        }
    }

        private function extractTextFromS3($s3Path)
    {
        if (!$s3Path) {
            \Log::error("Empty S3 Path received in extractTextFromS3");
            return '';
        }

        $bucket = env('AWS_BUCKET');
        $fullPath = ltrim($s3Path, '/');

        \Log::info("Extracting text from S3 path: " . $fullPath);

        // Initialize AWS Textract Client
        $textract = new TextractClient([
            'region' => env('AWS_TEXTRACT_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        try {
            // Start an asynchronous text detection job
            $result = $textract->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $bucket,
                        'Name' => $fullPath,
                    ],
                ],
            ]);

            $jobId = $result['JobId'];
            \Log::info("Textract Job Started. Job ID: " . $jobId);

            // Wait for the job to complete (polling)
            do {
                sleep(5); 
                $jobStatus = $textract->getDocumentTextDetection(['JobId' => $jobId]);
                $status = $jobStatus['JobStatus'];
                \Log::info("Textract Job Status: " . $status);
            } while ($status === 'IN_PROGRESS');

            // If job failed, log the error
            if ($status !== 'SUCCEEDED') {
                \Log::error("Textract Job Failed. Status: " . $status);
                return '';
            }

            // Extract text from the completed job
            $text = '';
            foreach ($jobStatus['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $text .= $block['Text'] . "\n";
                }
            }

            \Log::info("Extracted Text: " . substr($text, 0, 100) . "..."); // Log first 100 chars
            return $text;

        } catch (Exception $e) {
            \Log::error("Textract Error: " . $e->getMessage());
            return '';
        }
    }


        private function parseCVText($text)
    {
        if (empty($text)) {
            return [
                'Name' => 'N/A',
                'Degree' => 'N/A',
                'Job Title' => 'N/A',
                'Phone' => 'N/A',
                'Email' => 'N/A',
                'Education' => 'N/A',
                'Skills' => 'N/A',
                'Projects' => 'N/A'
            ];
        }

        //From the textract in AWS we can use qu

        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $lines = array_values($lines);

        $fullName = ($lines[0] ?? 'N/A') . ' ' . ($lines[1] ?? '');

        // Extract Phone
        $phone = 'N/A';
        foreach ($lines as $line) {
            if (preg_match('/(\+94\d{9}|07\d{8})/', $line, $matches)) {
                $phone = $matches[1];
                break;
            }
        }

        // Extract Email
        $email = 'N/A';
        foreach ($lines as $line) {
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $line, $matches)) {
                $email = $matches[0];
                break;
            }
        }


        //  Extract Degree Details
        $degreeKeywords = ['BSc', 'MSc', 'PhD'];
        $degree = [];
        foreach ($lines as $line) {
            foreach ($degreeKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $degree[] = $line;
                    break;
                }
            }
        }
        $degree = !empty($degree) ? implode("\n", $degree) : 'N/A';

        // Extract Education Details
        $educationKeywords = ['University', 'Institute', 'College', 'Academy'];
        $education = [];
        foreach ($lines as $line) {
            foreach ($educationKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $education[] = $line;
                    break;
                }
            }
        }
        $education = !empty($education) ? implode("\n", $education) : 'N/A';

        $jobTitleKeywords = ['Undergraduate', 'Engineer', 'Developer','Tech Lead','Project Manager'];
        $jobTitle = [];
        foreach ($lines as $line) {
            foreach ($jobTitleKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $jobTitle[] = $line;
                    break;
                }
            }
        }
        $jobTitle= !empty($jobTitle) ? implode("\n", $jobTitle) : 'N/A';

        //Extract Projects
        $projectKeywords = ['Project', 'System', 'Application', 'Software', 'Platform', 'Tool', 'Website', 'Portal', 'Framework', 'Solution','App'];
        $projects = [];

        foreach ($lines as $line) {
            foreach ($projectKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $projects[] = $line;
                    break;
                }
            }
        }

        $projects = !empty($projects) ? implode("\n", $projects) : 'N/A';

        // Extract Skills
        $skillsList = ['Python', 'Java', 'JavaScript', 'C++', 'C#', 'PHP', 'Swift', 'SQL', 'Node.js','HTML5','HTML','CSS','CSS3',''];
        $skills = [];
        foreach ($lines as $line) {
            foreach ($skillsList as $skill) {
                if (stripos($line, $skill) !== false) {
                    $skills[] = $skill;
                }
            }
        }
        $skills = !empty($skills) ? implode(', ', array_unique($skills)) : 'N/A';

        return [
            'Name' => $fullName,
            'Job Title' => $jobTitle,   
            'Degree' => $degree, 
            'Phone' => $phone,
            'Email' => $email,
            'Education' => $education,
            'Skills' => $skills,
            'Projects' => $projects
        ];
    }

    private function saveToGoogleSheets(Request $request, array $parsedData, string $cvUrl)
    {
        $client = new Google_Client();
        $client->setApplicationName('CV Submission');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('app/google-service-account.json'));
        $client->setAccessType('offline');

        $service = new Google_Service_Sheets($client);
        $spreadsheetId = '17S6oVZH0NkgLdoezx6kRltq-BuhkMUve4mjz9Iy8hGA';
        $range = 'JobApplicationInfo!A:H'; // Adjust based on your sheet structure

        // Data to insert into Google Sheets
        $values = [
            [
                $parsedData['Name'],
                $parsedData['Job Title'],
                $parsedData['Degree'],
                $request->phone,
                $parsedData['Email'],
                $parsedData['Education'],
                $parsedData['Skills'],
                $parsedData['Projects'],
                $cvUrl,
                now()->toDateTimeString()
            ]
        ];

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);

        $params = ['valueInputOption' => 'RAW'];

        // Append data to Google Sheet
        $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
    }

    private function sendWebhook(array $parsedData, string $cvUrl, string $submissionStatus)
    {
        $endpointUrl = 'https://rnd-assignment.automations-3d6.workers.dev/';
        $applicantEmail = 'chirancps2003@gmail.com'; 

        $payload = [
            "cv_data" => [
                "personal_info" => [
                    "name" => $parsedData['Name'],
                    "phone" => $parsedData['Phone'],
                    "email" => $parsedData['Email']
                ],
                "education" => explode("\n", $parsedData['Education']),  
                "skills" => explode(", ", $parsedData['Skills']),  
                "projects" => explode("\n", $parsedData['Projects']),  
                "qualifications" => explode("\n", $parsedData['Degree']),  
                "cv_public_link" => $cvUrl
            ],
            "metadata" => [
                "applicant_name" => $parsedData['Name'],
                "email" => $parsedData['Email'],
                "status" => $submissionStatus, 
                "cv_processed" => true,
                "processed_timestamp" => now()->toIso8601String()
            ]
        ];

        $response = Http::withHeaders([
            'X-Candidate-Email' => $applicantEmail
        ])->post($endpointUrl, $payload);

        if ($response->failed()) {
            \Log::error('Webhook failed: ' . $response->body());
        } else {
            \Log::info('Webhook sent successfully.');
        }
    }

    private function scheduleFollowUpEmail($email, $name)
    {
        // Schedule email for the next day at 9 AM applicant's local time
        $sendTime = Carbon::now()->addDay()->setHour(9)->setMinute(0);

        Mail::to($email)->later($sendTime, new FollowUpEmail($name));

        \Log::info("Follow-up email scheduled for: " . $email . " at " . $sendTime->toDateTimeString());
    }
}
