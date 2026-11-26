import './bootstrap';
import './crm-catalog';
import './crm-mockups';
import './order-status-confirmation';
import lamejs from 'lamejs';

window.lamejs = lamejs?.Mp3Encoder ? lamejs : (lamejs?.default ?? lamejs);
