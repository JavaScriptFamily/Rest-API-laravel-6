<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
class ContactusRequest extends Notification implements ShouldQueue
{
    use Queueable;
    
    /**
    * Create a new notification instance.
    *
    * @return void
    */
    protected $requestedData;
    public function __construct($requestedData)
    {
        $this->requestedData = $requestedData;
    }
    /**
    * Get the notification's delivery channels.
    *
    * @param  mixed  $notifiable
    * @return array
    */
    public function via($notifiable)
    {
        return ['mail'];
    }
    /**
    * Get the mail representation of the notification.
    *
    * @param  mixed  $notifiable
    * @return \Illuminate\Notifications\Messages\MailMessage
    */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Contact US Request")
            ->line("User Subject: ".$this->requestedData->subject)
            ->line("User Email: ".$this->requestedData->email)
            ->line("")
            ->line("User Message: ".$this->requestedData->description);
    }
/**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}