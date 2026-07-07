import './bootstrap';
import lamejs from 'lamejs';

window.lamejs = lamejs?.Mp3Encoder ? lamejs : (lamejs?.default ?? lamejs);
