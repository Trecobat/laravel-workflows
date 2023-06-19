<?php

namespace the42coders\Workflows\Tasks;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendMail extends Task
{
    public static $fields = [
        'Subject' => 'subject',
        'Recipients' => 'recipients',
        'Sender' => 'sender',
        'Content' => 'content',
        'Files' => 'files',
    ];

    public static $icon = '<i class="far fa-envelope"></i>';

    public function execute(): void
    {
        $dataBus = $this->dataBus;

        $recipients = $dataBus->get('recipients');
        $to = $recipients;
        if( $recipients instanceof User){
            Log::channel("workflow")->info("Je dois récupérer le mail de mon utilisateur.");
            $to = $recipients->use_email;
        }

        $to = match (App::environment()) {
            'local' => config("app.local_name"),
            'preprod', 'dev' => Auth::user()->use_email,
            default => $to ?? Auth::user()->use_email,
        };

        // Ajout d'un ligne pour dire que le mail auraiit été envoyé à ...
        if( App::environment("local","preprod","dev") ){
            $this->body.= "<br><hr><br>Environement " . App::environment() . "<br/>Cette notification aurait été envoyé à " . json_encode($to) ;
        }

        Log::channel("notification")->info("Envoi du mail " . $dataBus->get('subject') . " à $to en cours...");
        \Mail::html($dataBus->get('content'), function ($message) use ($dataBus,$to) {
            $message->subject($dataBus->get('subject'))
                ->to($to)
                ->from($dataBus->get('sender'));
            $counter = 1;
            if (is_array($dataBus->get('files'))) {
                Log::channel("workflow")->info("PJ" . json_encode( $dataBus->get('files'), JSON_PRETTY_PRINT ) );
                foreach ($dataBus->get('files') as $file) {

                    //$message->attachData($file, 'Datei_'.$counter);
                    $message->attachData($file, 'document_'.$counter.'.pdf');
                    $counter++;
                }
            }
        });
        Log::channel("notification")->info("Envoi du mail " . $dataBus->get('subject') . " à $to OK.");
    }
}
