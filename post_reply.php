<?php
$token = config('app.api_token') ?? config('services.omni.api_token');
echo "Token: " . substr($token??'',0,10) . "...\n";

$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Content-Type' => 'application/json',
])->post('http://127.0.0.1/api/v1/events/37/comments', [
    'author' => 'hermes',
    'body' => "Great question, boss. Let me break down the three-headed monster I found:\n\n**1. The Ghost Town (146 leads stuck at \"new\")**\nThese poor souls had emails in their pocket the whole time — imported with data, sitting pretty. But I was only looking for leads WITHOUT emails to enrich. So they got ignored like a party invitation that fell behind the fridge. 146 leads twiddling their thumbs while fully qualified. 🤦‍♂️\n\n**2. The Bouncer Problem (state machine said no)**\nWhen I tried to fast-track them from \"new\" → \"enriched\", the state machine bouncer stepped in: \"Sorry mate, you gotta go through the queue first. New → Enriching → Enriched. Them's the rules.\" So I was trying to skip the line like a tourist at Heathrow. 🙃\n\n**3. The Missing Phone Mystery (300 leads, no digits)**\nPhone enrichment? We don't have a pipeline for that. At all. It's like having a car with no wheels — you can sit in it, but you're not going anywhere. The original spreadsheets just didn't have phone data for about 40% of the leads. Short of hiring a private investigator, there's no robot arm that can call them up and ask for their digits. 🕵️\n\n**The Fix Applied:**\n✅ Found those 146 wallflowers, took them through the proper \"new → enriching → enriched\" VIP line, and they're now ready for email sequences.\n✅ 339 other leads genuinely had no email anywhere — tried 3 times each, came up empty. They've been marked \"no_email_found\" so we stop banging our head against the wall.\n\nBottom line: 416 enriched leads now ready to go. The ghost town has been evacuated. 🚀",
]);

echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";