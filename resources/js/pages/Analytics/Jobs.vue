<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Clock, Activity, CheckCircle, XCircle, Timer, RefreshCw, BarChart3, Calendar, ChevronDown, ChevronUp, Play } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Analytics', href: '/analytics' },
            { title: 'Scheduled Jobs', href: '/analytics/jobs' },
        ],
    },
})

interface JobRun {
    status: string
    exit_code: number | null
    duration_ms: number | null
    started_at: string | null
    finished_at: string | null
    output_summary: string | null
}

interface JobStats {
    total_runs: number
    runs_24h: number
    success_24h: number
    failed_24h: number
    running_24h: number
}

interface Job {
    name: string
    command: string
    description: string
    schedule: string
    schedule_label: string
    group: string
    last_run: JobRun | null
    stats: JobStats
}

interface PageStats {
    total_jobs: number
    total_runs: number
    success_count: number
    failed_count: number
    overall_health: number
}

const props = defineProps<{
    jobs: Job[]
    stats: PageStats
}>()

// Filtering
const activeGroup = ref<string | null>(null)
const groups = computed(() => {
    const g = new Set(props.jobs.map(j => j.group))
    return ['all', ...g]
})
const filteredJobs = computed(() => {
    if (!activeGroup.value || activeGroup.value === 'all') return props.jobs
    return props.jobs.filter(j => j.group === activeGroup.value)
})

// Run history
const selectedJob = ref<string | null>(null)
const timeRange = ref<'all' | 'today' | 'week'>('all')
const runHistory = ref<any[]>([])
const loadingHistory = ref(false)
const customFrom = ref('')
const customTo = ref('')
const runningJob = ref<string | null>(null)
const runResult = ref<string | null>(null)

async function runJob(job: Job) {
    runningJob.value = job.name
    runResult.value = null
    try {
        const res = await fetch(`/analytics/jobs/${encodeURIComponent(job.name)}/run`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        const data = await res.json()
        if (data.success) {
            runResult.value = `✅ Completed in ${formatDuration(data.duration_ms)}`
        } else {
            runResult.value = `❌ Failed (exit code: ${data.exit_code})`
        }
        // Refresh the page to update stats
        setTimeout(() => window.location.reload(), 1500)
    } catch (e) {
        runResult.value = '❌ Network error'
    } finally {
        runningJob.value = null
    }
}

async function fetchHistory(jobName: string) {
    selectedJob.value = jobName
    loadingHistory.value = true

    const params = new URLSearchParams()
    params.set('job', jobName)
    params.set('limit', '200')

    if (timeRange.value === 'today') {
        const today = new Date().toISOString().split('T')[0]
        params.set('from', today)
    } else if (timeRange.value === 'week') {
        const week = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]
        params.set('from', week)
    } else if (customFrom.value) {
        params.set('from', customFrom.value)
        if (customTo.value) params.set('to', customTo.value)
    }

    try {
        const res = await fetch(`/analytics/jobs/history?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        if (res.ok) {
            const data = await res.json()
            runHistory.value = data.runs || []
        }
    } catch (e) {
        runHistory.value = []
    } finally {
        loadingHistory.value = false
    }
}

function formatDuration(ms: number | null): string {
    if (!ms) return '—'
    if (ms < 1000) return `${ms}ms`
    const secs = (ms / 1000).toFixed(1)
    if (secs < '60') return `${secs}s`
    return `${Math.floor(ms / 60000)}m ${Math.floor((ms % 60000) / 1000)}s`
}

function formatTime(iso: string | null): string {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const groupLabels: Record<string, string> = {
    email: 'Email Pipeline',
    messaging: 'Messaging',
    leads: 'Lead Management',
    analytics: 'Analytics',
    system: 'System',
}

const groupIcons: Record<string, any> = {
    email: Clock,
    messaging: Activity,
    leads: BarChart3,
    analytics: BarChart3,
    system: RefreshCw,
}
</script>

<template>
    <Head title="Scheduled Jobs" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Scheduled Jobs</h1>
                <p class="text-sm text-muted-foreground">Cron job monitoring &amp; run history</p>
            </div>
        </div>

        <!-- Overall Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <Activity class="h-4 w-4 text-indigo-500" />
                    <span class="text-xs text-muted-foreground">Jobs</span>
                </div>
                <div class="mt-1 text-2xl font-bold">{{ stats.total_jobs }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <RefreshCw class="h-4 w-4 text-blue-500" />
                    <span class="text-xs text-muted-foreground">Total Runs</span>
                </div>
                <div class="mt-1 text-2xl font-bold">{{ stats.total_runs }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-4 w-4 text-emerald-500" />
                    <span class="text-xs text-muted-foreground">Successful</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.success_count }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <XCircle class="h-4 w-4 text-red-500" />
                    <span class="text-xs text-muted-foreground">Failed</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-red-600">{{ stats.failed_count }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <Activity class="h-4 w-4" :class="stats.overall_health >= 90 ? 'text-emerald-500' : stats.overall_health >= 70 ? 'text-amber-500' : 'text-red-500'" />
                    <span class="text-xs text-muted-foreground">Health</span>
                </div>
                <div class="mt-1 text-2xl font-bold" :class="stats.overall_health >= 90 ? 'text-emerald-600' : stats.overall_health >= 70 ? 'text-amber-600' : 'text-red-600'">{{ stats.overall_health }}%</div>
            </div>
        </div>

        <!-- Group Filter Tabs -->
        <div class="flex gap-2 flex-wrap">
            <button
                v-for="g in groups"
                :key="g"
                class="rounded-md px-3 py-1.5 text-xs font-medium capitalize transition-colors"
                :class="activeGroup === g || (!activeGroup && g === 'all') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                @click="activeGroup = g === 'all' ? null : g"
            >
                {{ g === 'all' ? 'All Jobs' : (groupLabels[g] || g) }}
            </button>
        </div>

        <!-- Job Cards -->
        <div class="grid gap-4">
            <div
                v-for="job in filteredJobs"
                :key="job.name"
                class="rounded-xl border bg-card p-5 shadow-sm"
                :class="{ 'ring-2 ring-blue-200': selectedJob === job.name }"
            >
                <div class="flex items-start justify-between">
                    <!-- Left: job info -->
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ groupLabels[job.group] || job.group }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-mono text-gray-600">{{ job.schedule_label }}</span>
                        </div>
                        <h3 class="mt-1 text-sm font-semibold text-gray-900">{{ job.name }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ job.description }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ job.command }}</p>
                    </div>

                    <!-- Right: status + stats -->
                    <div class="shrink-0 text-right ml-4">
                        <!-- Last run indicator -->
                        <div v-if="job.last_run" class="flex items-center gap-2 justify-end">
                            <span v-if="job.last_run.status === 'success'" class="inline-flex items-center gap-1 text-xs text-emerald-600"><CheckCircle class="h-3 w-3" /> Success</span>
                            <span v-else-if="job.last_run.status === 'failed'" class="inline-flex items-center gap-1 text-xs text-red-600"><XCircle class="h-3 w-3" /> Failed</span>
                            <span v-else class="inline-flex items-center gap-1 text-xs text-amber-600"><Timer class="h-3 w-3" /> Running</span>
                            <span class="text-[10px] text-muted-foreground">{{ formatTime(job.last_run.started_at) }}</span>
                        </div>
                        <div v-else class="text-xs text-muted-foreground">No runs yet</div>
                        <div v-if="job.last_run?.duration_ms" class="text-[10px] text-muted-foreground mt-0.5">
                            Duration: {{ formatDuration(job.last_run.duration_ms) }}
                        </div>
                    </div>
                </div>

                <!-- Quick stats row -->
                <div class="mt-3 flex items-center gap-4 text-[10px] text-muted-foreground border-t pt-2">
                    <span>Total: <strong>{{ job.stats.total_runs }}</strong></span>
                    <span>24h: <strong>{{ job.stats.runs_24h }}</strong></span>
                    <span v-if="job.stats.success_24h > 0" class="text-emerald-600">✅ <strong>{{ job.stats.success_24h }}</strong></span>
                    <span v-if="job.stats.failed_24h > 0" class="text-red-600">❌ <strong>{{ job.stats.failed_24h }}</strong></span>
                    <span v-if="job.stats.running_24h > 0" class="text-amber-600">⏳ <strong>{{ job.stats.running_24h }}</strong></span>
                    <button
                        class="ml-auto inline-flex items-center gap-1 text-blue-600 hover:underline"
                        @click="fetchHistory(job.name)"
                    >
                        <Clock class="h-3 w-3" /> View history
                    </button>
                    <button
                        class="inline-flex items-center gap-1 rounded bg-blue-600 px-2 py-1 text-[10px] font-medium text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="runningJob === job.name"
                        @click="runJob(job)"
                    >
                        <Play class="h-3 w-3" /> {{ runningJob === job.name ? 'Running...' : 'Run Now' }}
                    </button>
                    <div v-if="runResult && runningJob !== job.name" class="ml-2 text-xs font-medium" :class="runResult.startsWith('✅') ? 'text-emerald-600' : 'text-red-600'">{{ runResult }}</div>
                </div>

                <!-- Run history (expanded) -->
                <div v-if="selectedJob === job.name" class="mt-3 border-t pt-3">
                    <!-- Time range filters -->
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs text-muted-foreground">Filter:</span>
                        <button
                            v-for="range in (['all', 'today', 'week'] as const)"
                            :key="range"
                            class="rounded px-2 py-1 text-[10px] font-medium"
                            :class="timeRange === range ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100'"
                            @click="timeRange = range; fetchHistory(job.name)"
                        >
                            {{ range === 'all' ? 'All Time' : range === 'today' ? 'Today' : 'This Week' }}
                        </button>
                        <input v-model="customFrom" type="date" class="rounded border px-2 py-1 text-[10px]" @change="fetchHistory(job.name)" />
                        <span class="text-[10px] text-muted-foreground">to</span>
                        <input v-model="customTo" type="date" class="rounded border px-2 py-1 text-[10px]" @change="fetchHistory(job.name)" />
                    </div>

                    <div v-if="loadingHistory" class="flex items-center justify-center py-4">
                        <Timer class="h-4 w-4 animate-spin text-muted-foreground" />
                        <span class="ml-2 text-xs text-muted-foreground">Loading...</span>
                    </div>

                    <div v-else-if="runHistory.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                        No runs found for this time range.
                    </div>

                    <div v-else class="max-h-60 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b text-left text-[10px] uppercase tracking-wider text-muted-foreground">
                                    <th class="py-1 pr-2 font-medium">Time</th>
                                    <th class="py-1 px-2 font-medium">Status</th>
                                    <th class="py-1 px-2 font-medium text-right">Duration</th>
                                    <th class="py-1 px-2 font-medium text-right">Exit Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="run in runHistory" :key="run.id" class="border-b border-gray-50">
                                    <td class="py-1.5 pr-2 whitespace-nowrap">{{ formatTime(run.started_at) }}</td>
                                    <td class="py-1.5 px-2">
                                        <span v-if="run.status === 'success'" class="inline-flex items-center gap-0.5 text-emerald-600"><CheckCircle class="h-3 w-3" /> Success</span>
                                        <span v-else-if="run.status === 'failed'" class="inline-flex items-center gap-0.5 text-red-600"><XCircle class="h-3 w-3" /> Failed</span>
                                        <span v-else class="inline-flex items-center gap-0.5 text-amber-600"><Timer class="h-3 w-3" /> Running</span>
                                    </td>
                                    <td class="py-1.5 px-2 text-right">{{ formatDuration(run.duration_ms) }}</td>
                                    <td class="py-1.5 px-2 text-right font-mono">{{ run.exit_code ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>