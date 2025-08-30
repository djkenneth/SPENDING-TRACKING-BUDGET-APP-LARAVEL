# 💰 Spending Tracker Budget App

A comprehensive personal finance management application built with Laravel (backend) and Vue.js/Quasar Framework (frontend). Track expenses, manage budgets, set financial goals, and gain insights into your spending habits.

## 🌟 Features

### Core Functionality
- **📊 Transaction Management** - Track income and expenses with detailed categorization
- **💳 Multiple Account Support** - Manage multiple bank accounts, credit cards, and cash accounts
- **📈 Budget Tracking** - Set and monitor budgets by category with real-time alerts
- **🎯 Financial Goals** - Set savings goals and track progress
- **📱 Responsive Design** - Works seamlessly on desktop, tablet, and mobile devices
- **🔒 Secure Authentication** - JWT-based authentication with token refresh
- **📉 Analytics & Reports** - Visual insights with charts and spending trends
- **💱 Multi-Currency Support** - Handle transactions in different currencies
- **🔄 Offline Support** - Continue working offline with data sync when reconnected
- **📤 Data Export/Import** - Export to CSV and import transaction data

### Advanced Features
- **🔔 Smart Notifications** - Budget alerts and goal milestone notifications
- **📅 Bill Management** - Track recurring bills and payment reminders
- **💳 Debt Tracking** - Manage loans and credit card debts
- **📊 Spending Patterns Analysis** - AI-powered insights into spending habits
- **🏷️ Custom Categories** - Create and manage custom expense categories
- **📸 Receipt Storage** - Attach receipts to transactions
- **🔄 Bank Sync** - Import transactions from bank statements (CSV)
- **📈 Net Worth Tracking** - Monitor overall financial health

## 🚀 Tech Stack

### Backend (Laravel)
- **Framework**: Laravel 11.x
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 13+
- **Authentication**: Laravel Sanctum (JWT)
- **API**: RESTful API
- **Cache**: Redis (optional)
- **Queue**: Database/Redis queues for notifications

### Frontend (Vue.js + Quasar)
- **Framework**: Vue 3.4+ with Composition API
- **UI Framework**: Quasar 2.16+
- **State Management**: Pinia
- **HTTP Client**: Axios
- **Charts**: Chart.js 4.5+
- **Data Fetching**: TanStack Query (Vue Query)
- **Build Tool**: Vite
- **TypeScript**: Full TypeScript support
- **Offline Storage**: Dexie.js (IndexedDB)

## 📋 Prerequisites

- PHP >= 8.2
- Composer >= 2.5
- Node.js >= 18.0
- npm >= 9.0 or Yarn >= 1.22
- MySQL >= 8.0 or PostgreSQL >= 13
- Redis (optional, for caching and queues)

## 🛠️ Installation

### Backend Setup (Laravel)

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/spending-tracker-budget-app.git
cd spending-tracker-budget-app
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Environment configuration**
```bash
cp .env.example .env
```

4. **Configure your database in .env**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=budget_tracker
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Generate application key**
```bash
php artisan key:generate
```

6. **Run database migrations and seeders**
```bash
php artisan migrate --seed
```

7. **Generate JWT secret (for API authentication)**
```bash
php artisan jwt:secret
```

8. **Start the Laravel development server**
```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

### Frontend Setup (Vue.js + Quasar)

1. **Navigate to frontend directory**
```bash
cd frontend
# or if separate repository
cd spending-tracker-budget-app-frontend
```

2. **Install dependencies**
```bash
npm install
# or
yarn install
```

3. **Configure API endpoint**
Update the API URL in `quasar.config.ts`:
```javascript
env: {
  VITE_API_URL: ctx.dev ? 'http://127.0.0.1:8000/api' : 'https://your-production-api.com/api',
}
```

4. **Start the development server**
```bash
npm run dev
# or
yarn dev
# or
quasar dev
```

The application will open at `http://localhost:9000`

## 🏗️ Project Structure

### Backend Structure
```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AccountController.php
│   │   │       ├── TransactionController.php
│   │   │       ├── BudgetController.php
│   │   │       ├── CategoryController.php
│   │   │       ├── FinancialGoalController.php
│   │   │       └── ...
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Account.php
│   │   ├── Transaction.php
│   │   ├── Budget.php
│   │   └── ...
│   └── Services/
│       ├── BudgetService.php
│       ├── AnalyticsService.php
│       └── ...
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
```

### Frontend Structure
```
├── src/
│   ├── assets/
│   ├── boot/
│   │   ├── axios.ts
│   │   └── vue-query.ts
│   ├── components/
│   │   ├── TransactionForm.vue
│   │   ├── BudgetCard.vue
│   │   └── ...
│   ├── composables/
│   │   ├── useTransactions.ts
│   │   ├── useAccounts.ts
│   │   └── ...
│   ├── layouts/
│   │   ├── MainLayout.vue
│   │   └── AuthLayout.vue
│   ├── pages/
│   │   ├── DashboardPage.vue
│   │   ├── TransactionsPage.vue
│   │   ├── BudgetPage.vue
│   │   └── ...
│   ├── router/
│   ├── services/
│   │   ├── api.service.ts
│   │   ├── auth.service.ts
│   │   └── ...
│   └── stores/
│       ├── auth.ts
│       ├── settings.ts
│       └── ...
├── public/
└── quasar.config.ts
```

## 📚 API Documentation

### Authentication Endpoints
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/auth/refresh` - Refresh JWT token
- `GET /api/auth/user` - Get authenticated user

### Transaction Endpoints
- `GET /api/transactions` - List transactions (paginated)
- `POST /api/transactions` - Create transaction
- `GET /api/transactions/{id}` - Get transaction details
- `PUT /api/transactions/{id}` - Update transaction
- `DELETE /api/transactions/{id}` - Delete transaction
- `POST /api/transactions/bulk` - Bulk create transactions
- `POST /api/transactions/import` - Import from CSV

### Budget Endpoints
- `GET /api/budgets` - List budgets
- `POST /api/budgets` - Create budget
- `GET /api/budgets/{id}` - Get budget details
- `PUT /api/budgets/{id}` - Update budget
- `DELETE /api/budgets/{id}` - Delete budget
- `GET /api/budgets/{id}/analysis` - Get budget analysis

### Account Endpoints
- `GET /api/accounts` - List accounts
- `POST /api/accounts` - Create account
- `GET /api/accounts/{id}` - Get account details
- `PUT /api/accounts/{id}` - Update account
- `DELETE /api/accounts/{id}` - Delete account

[View full API documentation →](./docs/API.md)

## 🧪 Testing

### Backend Testing
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

### Frontend Testing
```bash
# Run unit tests
npm run test:unit

# Run e2e tests
npm run test:e2e

# Run with coverage
npm run test:coverage
```

## 📦 Building for Production

### Backend
```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Run migrations
php artisan migrate --force
```

### Frontend
```bash
# Build for production
npm run build
# or
quasar build

# Build specific platform
quasar build -m pwa    # Progressive Web App
quasar build -m spa    # Single Page Application
quasar build -m ssr    # Server Side Rendering
```

## 🚢 Deployment

### Using Docker
```bash
# Build and run with Docker Compose
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate
```

### Manual Deployment

1. **Configure environment variables** for production
2. **Set up web server** (Nginx/Apache)
3. **Configure SSL** certificate
4. **Set up process manager** (PM2/Supervisor)
5. **Configure cron jobs** for scheduled tasks
6. **Set up backup strategy**

[View deployment guide →](./docs/DEPLOYMENT.md)

## 🔧 Configuration

### Environment Variables

Key environment variables to configure:

```env
# Application
APP_NAME="Spending Tracker"
APP_ENV=production
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=budget_tracker
DB_USERNAME=username
DB_PASSWORD=password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525

# Currency Exchange API
EXCHANGE_RATE_API_KEY=your_api_key

# Storage
FILESYSTEM_DISK=local
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](./LICENSE) file for details.

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP Framework
- [Vue.js](https://vuejs.org) - The Progressive JavaScript Framework
- [Quasar Framework](https://quasar.dev) - Vue.js UI Framework
- [Chart.js](https://chartjs.org) - Simple yet flexible JavaScript charting
- [TanStack Query](https://tanstack.com/query) - Powerful data synchronization

## 📞 Support

For support and questions:
- 📧 Email: support@spendingtracker.com
- 💬 Discord: [Join our community](https://discord.gg/spendingtracker)
- 📖 Documentation: [docs.spendingtracker.com](https://docs.spendingtracker.com)
- 🐛 Issues: [GitHub Issues](https://github.com/yourusername/spending-tracker/issues)

## 🔄 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for a detailed list of changes and version history.

## 🗺️ Roadmap

- [ ] Mobile app (React Native/Flutter)
- [ ] AI-powered spending predictions
- [ ] Bank API integration
- [ ] Investment tracking
- [ ] Cryptocurrency support
- [ ] Family sharing & collaboration
- [ ] Advanced tax reporting
- [ ] Voice input for transactions

---

**Made with ❤️ by djkenneth**

*Building better financial habits, one transaction at a time.*