<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scheduled Job Definitions
    |--------------------------------------------------------------------------
    |
    | Each job defined here appears on the /analytics/jobs monitoring page.
    | When adding a new ->command() or ->job() to bootstrap/app.php, also
    | add its definition here so it appears on the dashboard.
    |
    | Fields:
    |   name        - unique job identifier (matches what TrackCronJobRuns resolves)
    |   command     - the artisan command or job class
    |   description - human-readable description (also set via ->description() in scheduler)
    |   schedule    - cron expression
    |   schedule_label - human-readable schedule description
    |   group       - grouping for the dashboard (email, messaging, leads, analytics, system)
    |
    */
    'jobs' => [
        [
            'name' => 'queue:prune-failed',
            'command' => 'queue:prune-failed --hours=336',
            'description' => 'Clean up failed queue jobs older than 14 days',
            'schedule' => '0 2:30 * * *',
            'schedule_label' => 'Daily at 2:30 AM',
            'group' => 'system',
        ],
        [
            'name' => 'emails:send-batch',
            'command' => 'emails:send-batch --limit=20',
            'description' => 'Send approved emails via SMTP2GO with safe-send discipline',
            'schedule' => '*/15 * * * *',
            'schedule_label' => 'Every 15 minutes',
            'group' => 'email',
        ],
        [
            'name' => 'emails:generate-content',
            'command' => 'emails:generate-content --limit=10',
            'description' => 'Check enriched leads for missing email content and log pipeline status (Hermes cron does the actual LLM generation every 30 min)',
            'schedule' => '*/30 * * * *',
            'schedule_label' => 'Every 30 minutes (pipeline check)',
            'group' => 'email',
        ],
        [
            'name' => 'emails:notify-telegram',
            'command' => 'emails:notify-telegram --limit=15',
            'description' => 'Send pending email approval requests to Telegram with content preview',
            'schedule' => '*/30 * * * *',
            'schedule_label' => 'Every 30 minutes',
            'group' => 'email',
        ],
        [
            'name' => 'ProcessSequenceProgressions',
            'command' => 'ProcessSequenceProgressions (job)',
            'description' => 'Progress email sequences: schedule next steps for leads (weekdays only)',
            'schedule' => '0 5:00 * * *',
            'schedule_label' => 'Daily at 5 AM (weekdays only)',
            'group' => 'email',
        ],
        [
            'name' => 'telegram:poll-approvals',
            'command' => 'telegram:poll-approvals',
            'description' => 'Poll Telegram for approval replies (text commands + inline callbacks)',
            'schedule' => '* * * * *',
            'schedule_label' => 'Every minute',
            'group' => 'messaging',
        ],
        [
            'name' => 'activity:daily-brief',
            'command' => 'activity:daily-brief',
            'description' => 'Generate daily system overview brief with funnel metrics',
            'schedule' => '0 7:00 * * *',
            'schedule_label' => 'Daily at 7 AM',
            'group' => 'system',
        ],
        [
            'name' => 'leads:score',
            'command' => 'leads:score',
            'description' => 'Recalculate lead scores (segment, completeness, engagement, email confidence)',
            'schedule' => '0 3:00 * * *',
            'schedule_label' => 'Daily at 3 AM',
            'group' => 'leads',
        ],
        [
            'name' => 'leads:monitor-mining',
            'command' => 'leads:monitor-mining --hours=2',
            'description' => 'Monitor lead mining pipeline: check Hermes mining crons (Rabbit + Deer, every 2h) are producing leads',
            'schedule' => '0 */2 * * *',
            'schedule_label' => 'Every 2 hours (pipeline check)',
            'group' => 'leads',
        ],
        [
            'name' => 'winloss:generate',
            'command' => 'winloss:generate',
            'description' => 'Generate win-loss report from reply outcomes and pipeline metrics',
            'schedule' => '0 6:00 * * 1',
            'schedule_label' => 'Weekly Monday 6 AM',
            'group' => 'analytics',
        ],
        [
            'name' => 'inbox:poll',
            'command' => 'inbox:poll --days=3 --limit=30',
            'description' => 'Poll IMAP inbox for lead replies and create Reply records',
            'schedule' => '*/10 * * * *',
            'schedule_label' => 'Every 10 minutes',
            'group' => 'messaging',
        ],
        [                                                                                                                                                                                                                                                                                                                                                                                        
            'name' => 'cron:cleanup-runs',                                                                                                                                                                                                                                                                                                                                                     
            'command' => 'cron:cleanup-runs --older-than=30',                                                                                                                                                                                                                                                                                                                                  
            'description' => 'Mark stuck running cron job records as failed (older than 30 min)',                                                                                                                                                                                                                                                                                              
            'schedule' => '*/30 * * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 30 minutes',                                                                                                                                                                                                                                                                                                                                            
            'group' => 'system',                                                                                                                                                                                                                                                                                                                                                               
        ],                                                                                                                                                                                                                                                                                                                                                                                     

        // ── Hiring Deer Pipeline ──                                                                                                                                                                                                                                                                                                                                                          
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:brightermonday',                                                                                                                                                                                                                                                                                                                                    
            'command' => 'leads:mine-hiring-signals --source=brightermonday',                                                                                                                                                                                                                                                                                                                  
            'description' => 'Hiring Deer: Mine BrighterMonday Kenya for hiring companies',                                                                                                                                                                                                                                                                                                    
            'schedule' => '0 */3 * * *',                                                                                                                                                                                                                                                                                                                                                       
            'schedule_label' => 'Every 3 hours',                                                                                                                                                                                                                                                                                                                                               
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:fuzu',                                                                                                                                                                                                                                                                                                                                              
            'command' => 'leads:mine-hiring-signals --source=fuzu',                                                                                                                                                                                                                                                                                                                            
            'description' => 'Hiring Deer: Mine Fuzu for hiring companies',                                                                                                                                                                                                                                                                                                                    
            'schedule' => '10 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+10 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:myjobmag',                                                                                                                                                                                                                                                                                                                                          
            'command' => 'leads:mine-hiring-signals --source=myjobmag',                                                                                                                                                                                                                                                                                                                        
            'description' => 'Hiring Deer: Mine MyJobMag Kenya for hiring companies',                                                                                                                                                                                                                                                                                                          
            'schedule' => '20 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+20 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:corporatestaffing',                                                                                                                                                                                                                                                                                                                                 
            'command' => 'leads:mine-hiring-signals --source=corporatestaffing',                                                                                                                                                                                                                                                                                                               
            'description' => 'Hiring Deer: Mine Corporate Staffing Services for hiring companies',                                                                                                                                                                                                                                                                                             
            'schedule' => '30 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+30 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:glassdoor',                                                                                                                                                                                                                                                                                                                                         
            'command' => 'leads:mine-hiring-signals --source=glassdoor',                                                                                                                                                                                                                                                                                                                       
            'description' => 'Hiring Deer: Mine Glassdoor/JobWebKenya for hiring companies',                                                                                                                                                                                                                                                                                                  
            'schedule' => '40 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+40 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:company_careers',                                                                                                                                                                                                                                                                                                                                   
            'command' => 'leads:mine-hiring-signals --source=company_careers',                                                                                                                                                                                                                                                                                                                 
            'description' => 'Hiring Deer: Mine JobWebKenya for hiring companies',                                                                                                                                                                                                                                                                                                            
            'schedule' => '50 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+50 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:google_jobs',                                                                                                                                                                                                                                                                                                                                       
            'command' => 'leads:mine-hiring-signals --source=google_jobs',                                                                                                                                                                                                                                                                                                                     
            'description' => 'Hiring Deer: Mine Google Jobs/JobWebKenya for hiring companies',                                                                                                                                                                                                                                                                                                
            'schedule' => '5 */3 * * *',                                                                                                                                                                                                                                                                                                                                                       
            'schedule_label' => 'Every 3 hours (+5 min offset)',                                                                                                                                                                                                                                                                                                                              
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'mine-hiring-signals:linkedin',                                                                                                                                                                                                                                                                                                                                          
            'command' => 'leads:mine-hiring-signals --source=linkedin',                                                                                                                                                                                                                                                                                                                        
            'description' => 'Hiring Deer: Mine LinkedIn Jobs for hiring companies',                                                                                                                                                                                                                                                                                                          
            'schedule' => '15 */3 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Every 3 hours (+15 min offset)',                                                                                                                                                                                                                                                                                                                             
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'leads:enrich-batch:deer',                                                                                                                                                                                                                                                                                                                                               
            'command' => 'leads:enrich-batch --segment=deer --limit=100',                                                                                                                                                                                                                                                                                                                      
            'description' => 'Hiring Deer: Enrich newly mined leads with website/email data',                                                                                                                                                                                                                                                                                                 
            'schedule' => '0 2:00 * * *',                                                                                                                                                                                                                                                                                                                                                      
            'schedule_label' => 'Daily at 2 AM',                                                                                                                                                                                                                                                                                                                                               
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
        [                                                                                                                                                                                                                                                                                                                                                                                      
            'name' => 'leads:hiring-signal-digest',                                                                                                                                                                                                                                                                                                                                            
            'command' => 'leads:hiring-signal-digest',                                                                                                                                                                                                                                                                                                                                         
            'description' => 'Hiring Deer: Publish consolidated daily digest to activity feed',                                                                                                                                                                                                                                                                                               
            'schedule' => '10 2:00 * * *',                                                                                                                                                                                                                                                                                                                                                     
            'schedule_label' => 'Daily at 2:10 AM',                                                                                                                                                                                                                                                                                                                                            
            'group' => 'hiring-deer',                                                                                                                                                                                                                                                                                                                                                          
        ],                                                                                                                                                                                                                                                                                                                                                                                     
    ],                                                                                                                                                                                                                                                                                                                                                                                         
];
