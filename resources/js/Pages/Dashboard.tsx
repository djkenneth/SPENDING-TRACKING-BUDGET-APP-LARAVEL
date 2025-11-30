import { Head } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { ArrowUpIcon, CircleFadingArrowUpIcon } from "lucide-react";

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="min-h-screen bg-background p-8">
                <div className="max-w-7xl mx-auto space-y-6">
                    <h1 className="text-4xl font-bold">
                        Welcome to Your Dashboard!
                    </h1>

                    <Card className="bg-cyan-50">
                        <CardHeader>
                            <CardTitle>
                                React + shadcn/ui is Working! 🎉
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground mb-4">
                                Your Inertia.js + React + shadcn/ui setup is
                                complete.
                            </p>
                            <Button variant="default" className="bg-blue-300">
                                Test Button
                            </Button>
                            <Button variant="destructive">Destructive</Button>
                        </CardContent>
                    </Card>
                    <Button variant="destructive">Destructive</Button>
                    <Button variant="link">Link</Button>
                    <Button variant="outline" size="icon">
                        <CircleFadingArrowUpIcon />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="rounded-full"
                    >
                        <ArrowUpIcon />
                    </Button>
                </div>
            </div>
        </>
    );
}
