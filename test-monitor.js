import { io } from 'socket.io-client';

const key = process.env.SOCKET_IO_KEY || '';
const port = process.env.SOCKET_IO_PORT || '3001';

const studentSocket = io(`http://localhost:${port}`, {
    query: { key, exam_id: '1', student_id: '100' }
});

const proctorSocket = io(`http://localhost:${port}`, {
    query: { key, exam_id: '1' }
});

let studentStatusSent = false;

studentSocket.on('connect', () => {
    console.log('[Student] Connected:', studentSocket.id);
    
    setTimeout(() => {
        console.log('[Student] Sending status_update...');
        studentSocket.emit('status_update', {
            is_focused: true,
            current_question_id: 'q1',
            answered_count: 5
        });
        studentStatusSent = true;
    }, 1000);
});

proctorSocket.on('connect', () => {
    console.log('[Proctor] Connected:', proctorSocket.id);
    
    // Listen for real-time status updates
    proctorSocket.on('student_status', (payload) => {
        console.log('[Proctor] Received real-time student_status:', payload);
        
        // After receiving status, request full active student list
        console.log('[Proctor] Requesting active students...');
        proctorSocket.emit('request_active_students', { exam_id: '1' }, (response) => {
            console.log('[Proctor] Received active students list:', response);
            
            // Clean up and exit
            studentSocket.disconnect();
            proctorSocket.disconnect();
            process.exit(0);
        });
    });
});

setTimeout(() => {
    console.error('Test timeout');
    process.exit(1);
}, 5000);
