<!-- 
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApplicationController;
use Illuminate\Http\Request;

class ProcessCVSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $formData;
    protected $cvUrl;
    protected $s3Path;

    public function __construct(array $formData, $cvUrl, $s3Path)
    {
        $this->formData = $formData;
        $this->cvUrl = $cvUrl;
        $this->s3Path = $s3Path;
    }

    public function handle()
    {
        try {
            $appController = new ApplicationController();

            // Extract text from S3
            $text = $appController->extractTextFromS3($this->s3Path);

            // Parse extracted text
            $parsedData = $appController->parseCVText($text);

            // Save parsed data to Google Sheets
            $appController->saveToGoogleSheets($this->formData, $parsedData, $this->cvUrl);

            // Send Webhook
            $appController->sendWebhook($parsedData, $this->cvUrl, 'testing');

            // Send follow-up email
            //$appController->scheduleFollowUpEmail($this->formData['email'], $this->formData['name']);

        } catch (\Exception $e) {
            Log::error("Error processing CV: " . $e->getMessage());
        }
    }
} -->
