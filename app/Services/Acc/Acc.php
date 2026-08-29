<?php

namespace App\Services\Acc;

use App\Services\Acc\AccTransaction;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class Acc implements LedgerInterface
{

    protected $host;

    protected $key;


    public function __construct()
    {
        // M15: managed via Settings UI, falling back to .env/config.
        $this->host = setting('acc_host', config('services.acc.host'));
        $this->key = setting('acc_key', config('services.acc.key'));
    }

    private function headers()
    {
        return [
            'Authorization' => 'Bearer '.$this->key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/vnd.api+json',
        ];
    }
    public function withdraw(AccTransaction $data)
    {
        $client = new Client();
        $body =  [
            "error_if_duplicate_hash"=> true,
            "apply_rules"=> false,
            "fire_webhooks"=> false,
            "transactions" => [$data],
        ];

        $request = new Request('POST', $this->host.'/api/v1/transactions', $this->headers(), json_encode($body));
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody());
    }
    public function deposit(AccTransaction $data)
    {
        $client = new Client();
        $body =  [
            "error_if_duplicate_hash"=> true,
            "apply_rules"=> false,
            "fire_webhooks"=> false,
            "transactions" => [$data],
        ];
        $request = new Request('POST', $this->host.'/api/v1/transactions', $this->headers(), json_encode($body));
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody());
    }

    public function getAccounts(): array
    {
        try {
            $client = new Client();
            $url = $this->host.'/api/v1/accounts?limit=100&page=1&type=all';
            $request =new Request('GET', $url, $this->headers(), '');
            $res = $client->sendAsync($request)->wait();
            $values =  json_decode($res->getBody());

            $data  = [];
            foreach ($values->data as $value) {
                $data[$value->id] = "({$value->id}) {$value->attributes->name} - {$value->attributes->account_number} - {$value->attributes->type}";
            }
            return $data;
        }catch (\Exception $exception){
            Log::error($exception->getMessage());
            return [];
        }
    }

    public function getTransaction(string $id)
    {
          try {
              $client = new Client();
              $url = $this->host.'/api/v1/transactions/'.$id;
              $request =new Request('GET', $url, $this->headers(), '');
              $res = $client->sendAsync($request)->wait();
              return json_decode($res->getBody());
          }catch (\Exception $exception){
              Log::error($exception->getMessage());
              return [];
          }
    }


    public function updateTransaction(string $id, AccTransaction $transaction){
        $client = new Client();

        $oldTransaction = $this->getTransaction($id);

        $updatedTransaction = $oldTransaction->data->attributes->transactions[0];

        $updatedTransaction->type = $transaction->type ?? $updatedTransaction->type;
        $updatedTransaction->date = $transaction->date ?? $updatedTransaction->date;
        $updatedTransaction->amount = $transaction->amount ?? $updatedTransaction->amount;
        $updatedTransaction->description = $transaction->description ?? $updatedTransaction->description;
        $updatedTransaction->source_id = $transaction->source_id ?? $updatedTransaction->source_id;
        $updatedTransaction->destination_id = $transaction->destination_id ?? $updatedTransaction->destination_id;
        $updatedTransaction->tags = $transaction->tags ?? $updatedTransaction->tags;
        $updatedTransaction->notes = $transaction->notes ?? $updatedTransaction->notes;
        $updatedTransaction->internal_reference = $transaction->internal_reference ?? $updatedTransaction->internal_reference;
        $updatedTransaction->external_id = $transaction->external_id ?? $updatedTransaction->external_id;

        $body =  [
            "error_if_duplicate_hash"=> true,
            "apply_rules"=> false,
            "fire_webhooks"=> false,
            "transactions" => [$updatedTransaction],
        ];
        $request = new Request('PUT',
            $this->host.'/api/v1/transactions/'.$id,
            $this->headers(),
            json_encode($body)
        );
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody());
    }

    public function delete(string $id){
        $client = new Client();
        $request = new Request('DELETE',
            $this->host.'/api/v1/transactions/'.$id,
            $this->headers(),
            ""
        );
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody());
    }
}
