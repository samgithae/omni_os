import subprocess, json, sys

# Read token
token = subprocess.run(["grep", "OMNI_API_TOKEN", "/srv/omni_os/.env"], capture_output=True, text=True).stdout.strip().split("=", 1)[1]

# Lead data from DB query
leads = [
    {"id": 1634, "company": "Geovani Training Institute", "email": "info@geovanitraininginstitute.co.ke", "segment": "rabbit", "category": "Private Training Provider", "country": "Kenya", "city": "Nairobi", "website": "https://www.geovanitraininginstitute.co.ke"},
    {"id": 1866, "company": "Training Force", "email": "info@trainingforce.co.za", "segment": "rabbit", "category": "Corporate Training Company", "country": "South Africa", "city": "Johannesburg", "website": "https://trainingforce.co.za"},
    {"id": 1871, "company": "Zambia Development Agency", "email": "info@zda.org.zm", "segment": "rabbit", "category": "Skills Development Provider", "country": "Zambia", "city": "Lusaka", "website": "https://zda.org.zm"},
    {"id": 1874, "company": "Coventry Academy", "email": "info@coventryacademy.com", "segment": "rabbit", "category": "TVET Institution", "country": "Tanzania", "city": "Dar es Salaam", "website": "https://coventryacademy.com"},
    {"id": 1944, "company": "Incontext", "email": "info@incontext.co.ke", "segment": "rabbit", "category": "Training Institute", "country": "Kenya", "city": "Kenya", "website": "https://www.incontext.co.ke/countries/training-courses-kenya/"},
    {"id": 1989, "company": "Muyeyevtc.Ac", "email": "muyeyevtc@gmail.com", "segment": "rabbit", "category": "Training Institute", "country": "Kenya", "city": "Malindi", "website": "https://www.muyeyevtc.ac.ke/"},
    {"id": 1995, "company": "Wrti.Go", "email": "admin@kingsteruni.edu", "segment": "rabbit", "category": "Training Institute", "country": "Kenya", "city": "Naivasha", "website": "https://wrti.go.ke/"},
]

def generate_emails(lead):
    c = lead["company"]
    cat = lead["category"]
    country = lead["country"]
    city = lead["city"]
    
    # Email 1: Observation + open question
    if "Geovani" in c:
        e1_subj = "Training delivery at Geovani Training Institute"
        e1_body = "Hi team,\n\nI came across Geovani Training Institute and noticed your focus on professional development programs in Nairobi. Private training providers like yours play a critical role in bridging Kenya's skills gap.\n\nI am curious: how do you currently manage student enrollment, attendance tracking, and certificate issuance across your programs? Are these running on separate tools or a single platform?\n\nWould love to learn more about how you operate.\n\nSamuel"
    elif "Training Force" in c:
        e1_subj = "Training Force and South Africa's skills landscape"
        e1_body = "Hi team,\n\nTraining Force has built a strong reputation as one of South Africa's leading corporate training companies. Your reach across Johannesburg and beyond is impressive.\n\nI am curious about one thing: with SETA accreditation requirements and corporate clients demanding verifiable training records, how do you currently handle digital certificate management and compliance tracking?\n\nWould enjoy hearing how you have approached this.\n\nSamuel"
    elif "Zambia" in c:
        e1_subj = "ZDA's role in Zambia's skills development"
        e1_body = "Hi team,\n\nThe Zambia Development Agency plays a unique role at the intersection of investment promotion and skills development. That dual mandate is rare and powerful.\n\nI am curious: as ZDA works with training providers and employers across Zambia, how do you currently track training outcomes and verify that skills programs are delivering measurable results?\n\nWould value hearing your perspective on this.\n\nSamuel"
    elif "Coventry" in c:
        e1_subj = "Coventry Academy's TVET impact in Tanzania"
        e1_body = "Hi team,\n\nCoventry Academy stands out as a TVET institution making real impact in Dar es Salaam. Tanzania's push for vocational skills development creates huge opportunity for institutions like yours.\n\nI am curious: how do you currently manage student records, competency assessments, and certificate issuance across your programs? Is this running smoothly or are there friction points?\n\nWould love to hear about your experience.\n\nSamuel"
    elif "Incontext" in c:
        e1_subj = "Incontext and Kenya's training ecosystem"
        e1_body = "Hi team,\n\nIncontext has carved out a strong position delivering training courses across Kenya. Your multi-country presence suggests you understand what it takes to scale quality education.\n\nI am curious: as you run programs across different locations, how do you currently handle learner registration, progress tracking, and certification? Is it centralised or location-specific?\n\nWould enjoy learning about your approach.\n\nSamuel"
    elif "Muyeyevtc" in c:
        e1_subj = "Muyeyevtc.Ac and vocational training in Malindi"
        e1_body = "Hi team,\n\nMuyeyevtc.Ac is doing important work bringing vocational training to Malindi and the coastal region. Local training institutions like yours are the backbone of Kenya's TVET ecosystem.\n\nI am curious: how do you currently manage student admissions, course scheduling, and certificate issuance? Are you using a digital system or running things manually?\n\nWould love to hear how you operate day to day.\n\nSamuel"
    elif "Wrti" in c:
        e1_subj = "Wrti.Go and training delivery in Naivasha"
        e1_body = "Hi team,\n\nWrti.Go serves the Naivasha region with training programs that matter to local communities and employers. Regional training institutes like yours fill a gap that larger institutions often miss.\n\nI am curious: how do you currently handle learner management, from enrollment through to certification? What tools are you using to keep everything organised?\n\nWould enjoy hearing about your setup.\n\nSamuel"
    else:
        e1_subj = c + " and your training approach"
        e1_body = "Hi team,\n\nI came across " + c + " and was impressed by your work in the training space. Your focus on delivering quality programs is clear.\n\nI am curious: how do you currently manage your training operations, from learner enrollment through to certification? I would love to hear about your approach.\n\nSamuel"

    # Email 2: Peer story + directional close
    if "Geovani" in c:
        e2_subj = "How a Nairobi training provider streamlined ops"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A training provider in Nairobi, similar in size to Geovani Training Institute, was spending hours each week on manual attendance sheets and certificate generation. They switched to a digital platform and cut admin time by 60% while giving students instant access to verifiable certificates.\n\nIt made me think of you. If streamlining your training operations is something you are exploring, I would be happy to share what that looks like in practice.\n\nSamuel"
    elif "Training Force" in c:
        e2_subj = "How a SA training company digitised compliance"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A South African corporate training company, similar in scale to Training Force, was struggling with SETA compliance documentation and client reporting. They moved to a digital platform and now generate audit-ready reports in minutes instead of days.\n\nIt made me think of you. If simplifying compliance and client reporting is on your radar, I would be happy to share how they did it.\n\nSamuel"
    elif "Zambia" in c:
        e2_subj = "How a skills body digitised training tracking"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A skills development organisation in East Africa, similar in mandate to ZDA, was tracking training outcomes across dozens of providers using spreadsheets. They moved to a centralised digital platform and now have real-time visibility into program performance.\n\nIt made me think of you. If better training data and reporting is something you are working toward, I would be happy to share what that looks like.\n\nSamuel"
    elif "Coventry" in c:
        e2_subj = "How a TVET college digitised student records"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A TVET institution in East Africa, similar to Coventry Academy, was managing student records and assessments on paper. They moved to a digital platform and now have automated certificate issuance and real-time student progress tracking.\n\nIt made me think of you. If modernising your student management is something you are considering, I would be happy to share how they approached it.\n\nSamuel"
    elif "Incontext" in c:
        e2_subj = "How a multi-location trainer centralised ops"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A training institute running programs across multiple locations, similar to Incontext, was struggling with fragmented learner data across sites. They moved to a single digital platform and now have unified visibility across all their programs.\n\nIt made me think of you. If centralising your training operations is something you are exploring, I would be happy to share what that looks like in practice.\n\nSamuel"
    elif "Muyeyevtc" in c:
        e2_subj = "How a coastal TVET went digital"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A vocational training centre on Kenya's coast, similar to Muyeyevtc.Ac, was running admissions and certification entirely on paper. They moved to a digital platform and now process student enrollments in minutes and issue verifiable digital certificates.\n\nIt made me think of you. If going digital with your training operations is something you are considering, I would be happy to share how they did it.\n\nSamuel"
    elif "Wrti" in c:
        e2_subj = "How a regional training institute modernised"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A regional training institute in Kenya, similar to Wrti.Go, was managing learner records and certificates manually. They moved to a digital platform and now have automated workflows that save their team hours each week.\n\nIt made me think of you. If modernising your training operations is on your radar, I would be happy to share what that looks like.\n\nSamuel"
    else:
        e2_subj = "How a training provider streamlined their ops"
        e2_body = "Hi team,\n\nI wanted to share a quick story. A training provider similar to " + c + " was spending too much time on admin. They moved to a digital platform and cut their operational overhead significantly while improving the learner experience.\n\nIt made me think of you. If streamlining your training operations is something you are exploring, I would be happy to share how they did it.\n\nSamuel"

    # Email 3: Monday morning vision + CTAs
    e3_subj = "A quick Monday thought for " + c
    e3_body = "Hi team,\n\nImagine it is Monday morning. You open your dashboard and see every student enrollment, attendance record, assessment result, and certificate issued across all your programs, in one place, updated in real time. No chasing spreadsheets, no manual certificate generation, no compliance headaches.\n\nThat is what we built UjuziPlus for.\n\nIf you are curious, I would love to show you a 20-minute demo. You can grab a slot on my calendar here: https://calendar.app.google/A9P72gVWPVm9pPBw6\n\nOr if WhatsApp is easier, message me here: https://wa.link/dshsuz\n\nSamuel"

    # Email 4: Clean breakup + referral ask
    e4_subj = "Closing the loop, " + c
    e4_body = "Hi team,\n\nI have reached out a few times and I do not want to keep filling your inbox if the timing is not right.\n\nIf UjuziPlus is not a fit for " + c + " right now, no problem at all. But if you know another training provider, consultant, or coach who might benefit from a platform that handles learner management, digital certificates, and compliance tracking, I would be grateful for the introduction.\n\nWishing you continued success with your programs.\n\nSamuel"

    return [
        {"step": 1, "subject": e1_subj[:50], "body": e1_body},
        {"step": 2, "subject": e2_subj[:50], "body": e2_body},
        {"step": 3, "subject": e3_subj[:50], "body": e3_body},
        {"step": 4, "subject": e4_subj[:50], "body": e4_body},
    ]

auth = "Authorization: Bearer {}".format(token)
results = {"success": 0, "failed": 0, "details": []}

for lead in leads:
    lid = lead["id"]
    c = lead["company"]
    emails = generate_emails(lead)
    payload = json.dumps({"emails": emails})
    
    r = subprocess.run(["curl", "-s", "-X", "POST",
        "http://127.0.0.1/api/v1/leads/{}/email-content-batch".format(lid),
        "-H", auth, "-H", "Content-Type: application/json",
        "-d", payload], capture_output=True, text=True)
    
    print("Lead {} ({}): {}".format(lid, c, r.stdout.strip()))
    
    try:
        resp = json.loads(r.stdout)
        if "message" in resp and "error" not in resp.get("message", "").lower():
            results["success"] += 1
            results["details"].append({"id": lid, "company": c, "status": "success", "response": resp})
        else:
            results["failed"] += 1
            results["details"].append({"id": lid, "company": c, "status": "failed", "response": resp})
    except:
        results["failed"] += 1
        results["details"].append({"id": lid, "company": c, "status": "failed", "response": r.stdout})

print("\n=== SUMMARY ===")
print("Succeeded: {}".format(results["success"]))
print("Failed: {}".format(results["failed"]))
for d in results["details"]:
    print("  {} ({}): {}".format(d["id"], d["company"], d["status"]))
