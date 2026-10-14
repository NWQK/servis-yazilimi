<?php
namespace App\Services;
use Symfony\Component\Mailer\{Envelope,SentMessage};
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
class HealthSmtpTransport implements TransportInterface
{
    public function __construct(private TransportInterface $transport) {}
    public function send(RawMessage $message,Envelope $envelope=null): ?SentMessage
    {
        try { $sent=$this->transport->send($message,$envelope); $this->record($sent!==null,$sent!==null ? 'accepted' : 'not_sent');return $sent; }
        catch(\Throwable $e){$this->record(false,'transport_error');throw $e;}
    }
    private function record(bool $success,string $code): void
    {
        if(\DB::transactionLevel()>0) \DB::afterCommit(fn()=>SystemHealth::record('smtp',$success,$code));
        else SystemHealth::record('smtp',$success,$code);
    }
    public function __toString(): string { return 'smtp'; }
    public function __call(string $method,array $arguments)
    {
        $result=$this->transport->$method(...$arguments);
        return $result===$this->transport ? $this : $result;
    }
}
