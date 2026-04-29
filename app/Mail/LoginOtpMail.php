<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $otp;
    public string $systemName;
    public string $imageUrl;
    public string $subjectLine;

    public function __construct(
        string $name,
        string $otp,
        string $systemName,
        string $imageUrl,
        string $subjectLine
    ) {
        $this->name        = $name;
        $this->otp         = $otp;
        $this->systemName  = $systemName;
        $this->imageUrl    = $imageUrl;
        $this->subjectLine = $subjectLine;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from(
            config('mail.from.address'),
            config('mail.from.name')
        )
            ->subject($this->subjectLine)
            ->view('content.emails.login-otp')
            ->with([
                'name'       => $this->name,
                'otp'        => $this->otp,
                'systemName' => $this->systemName,
                'imageUrl'   => $this->imageUrl,
            ]);
    }
}
