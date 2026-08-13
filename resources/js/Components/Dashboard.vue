<script setup>
defineProps({
    invigilatorName: { type: String, required: true },
    verified: { type: Boolean, required: true },
    schedule: { type: Array, required: true },
});

defineEmits(['search-again']);
</script>

<template>
    <section class="dashboard">
        <header>
            <h2>{{ invigilatorName }}</h2>
            <span :class="verified ? 'badge badge--verified' : 'badge badge--unverified'">
                {{ verified ? '✅ Verified vs Excel' : '⚠️ Not Verified vs Excel' }}
            </span>
        </header>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Course Code(s)</th>
                    <th>Room</th>
                    <th>Lecturer</th>
                    <th>No. of Students</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="duty in schedule" :key="duty.course_codes + duty.date + duty.start_time">
                    <td>{{ duty.date }}</td>
                    <td>{{ duty.start_time }}–{{ duty.end_time }}</td>
                    <td>{{ duty.course_codes }}</td>
                    <td>{{ duty.room }}</td>
                    <td>{{ duty.lecturer_name ?? '—' }}</td>
                    <td>{{ duty.student_count ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <footer>
            <button type="button" @click="$emit('search-again')">Search Again</button>
        </footer>
    </section>
</template>