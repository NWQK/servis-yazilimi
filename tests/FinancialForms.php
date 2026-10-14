<?php
namespace Tests;
use App\Models\{Invoice,InvoiceItem};
use App\Services\InvoiceJournal;
use Illuminate\Http\Request;
trait FinancialForms
{
    // Legacy business tests submit a fresh form; concurrency tests explicitly reuse stale tokens.
    private function financialForm(string $method,string $uri,array $data): array
    {
        $route=app('router')->getRoutes()->match(Request::create($uri,$method));
        $id=$route->parameter('id')??$route->parameter('invoice');
        if($route->getName()==='invoice.update') $id=decrypt($id);
        if($route->getName()==='invoice.item.destroy') $id=InvoiceItem::find($data['id']??null)?->invoice_id;
        $invoice=$id ? Invoice::find($id) : null;
        return $data+['operation_token'=>InvoiceJournal::token(),'invoice_revision'=>$invoice?->revision ?? 0];
    }
    protected function postInvoice($uri,array $data=[],array $headers=[]){return $this->post($uri,$this->financialForm('POST',$uri,$data),$headers);}
    protected function putInvoice($uri,array $data=[],array $headers=[]){return $this->put($uri,$this->financialForm('PUT',$uri,$data),$headers);}
    protected function deleteInvoice($uri,array $data=[],array $headers=[]){return $this->delete($uri,$this->financialForm('DELETE',$uri,$data),$headers);}
}
